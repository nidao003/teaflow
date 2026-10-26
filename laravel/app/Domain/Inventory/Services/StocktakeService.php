<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\BizType;
use App\Domain\Inventory\Enums\ItemType;
use App\Domain\Inventory\Enums\StocktakeStatus;
use App\Domain\Inventory\Exceptions\InvalidStateTransitionException;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\StockItem;
use App\Domain\Inventory\Models\Stocktake;
use App\Domain\Inventory\Models\StocktakeItem;
use Illuminate\Support\Facades\DB;

/**
 * 盘点服务：create（快照账面数）→ count（录入实盘）→ adjust（差异调账）。
 * 差异通过 StockService::adjust() 写 adjust 流水，盘点单保留账面/实盘/差异留痕。
 */
class StocktakeService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * 创建盘点单（counting），并快照账面数量。
     * 不传 items 时盘点该仓库全部库存行。
     *
     * @param  array{
     *     warehouse_id:int, item_type?:string, operator_id?:int|null, remark?:string|null,
     *     items?: array<int, array{item_type?:string, item_id:int, unit?:string, unit_cost?:float|int|string}>
     * }  $data
     */
    public function create(array $data): Stocktake
    {
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        if ($warehouseId <= 0) {
            throw new InventoryException('缺少 warehouse_id');
        }

        $filterType = isset($data['item_type']) ? ItemType::fromValue($data['item_type']) : null;
        $lines = $data['items'] ?? null;

        return DB::transaction(function () use ($data, $warehouseId, $filterType, $lines): Stocktake {
            $stocktake = Stocktake::create([
                'stocktake_no' => $this->generateNo(),
                'warehouse_id' => $warehouseId,
                'status' => StocktakeStatus::COUNTING,
                'total_items' => 0,
                'operator_id' => isset($data['operator_id']) ? (int) $data['operator_id'] : null,
                'remark' => $data['remark'] ?? null,
            ]);

            $count = 0;

            if (is_array($lines) && $lines !== []) {
                foreach ($lines as $line) {
                    $itemType = ItemType::fromValue($line['item_type'] ?? $filterType);
                    $itemId = (int) ($line['item_id'] ?? 0);
                    if ($itemId <= 0) {
                        throw new InventoryException('盘点行缺少 item_id');
                    }

                    $stock = StockItem::query()
                        ->where('warehouse_id', $warehouseId)
                        ->where('item_type', $itemType->value)
                        ->where('item_id', $itemId)
                        ->first();

                    StocktakeItem::create([
                        'stocktake_id' => $stocktake->id,
                        'item_type' => $itemType,
                        'item_id' => $itemId,
                        'unit' => $line['unit'] ?? $stock?->unit ?? 'unit',
                        'book_qty' => $stock !== null ? (float) $stock->qty : 0,
                        'counted_qty' => null,
                        'diff_qty' => 0,
                        'unit_cost' => $stock !== null ? (float) $stock->avg_cost : $this->money($line['unit_cost'] ?? 0),
                        'diff_amount' => 0,
                        'adjusted' => false,
                    ]);

                    $count++;
                }
            } else {
                $query = StockItem::query()->where('warehouse_id', $warehouseId);
                if ($filterType !== null) {
                    $query->where('item_type', $filterType->value);
                }

                foreach ($query->get() as $stock) {
                    StocktakeItem::create([
                        'stocktake_id' => $stocktake->id,
                        'item_type' => $stock->item_type,
                        'item_id' => $stock->item_id,
                        'unit' => $stock->unit,
                        'book_qty' => (float) $stock->qty,
                        'counted_qty' => null,
                        'diff_qty' => 0,
                        'unit_cost' => (float) $stock->avg_cost,
                        'diff_amount' => 0,
                        'adjusted' => false,
                    ]);

                    $count++;
                }
            }

            $stocktake->forceFill(['total_items' => $count])->save();

            return $stocktake->refresh()->load('items');
        });
    }

    /**
     * 录入实盘数量：counting → counted。
     * $counts 支持 [stocktake_item_id => 实盘数] 或 [['stocktake_item_id'=>..,'counted_qty'=>..]]。
     *
     * @param  array<int|string, mixed>  $counts
     */
    public function count(Stocktake $stocktake, array $counts, ?int $operatorId = null): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $counts, $operatorId): Stocktake {
            $stocktake = $this->lockStocktake($stocktake);
            $from = $this->status($stocktake);

            // 已盘但未调账时允许重新计数（先回退到 counting）
            if ($from === StocktakeStatus::COUNTED) {
                $stocktake->forceFill(['status' => StocktakeStatus::COUNTING])->save();
                $from = StocktakeStatus::COUNTING;
            }

            if ($from !== StocktakeStatus::COUNTING) {
                throw InvalidStateTransitionException::for('盘点单', $from->value, StocktakeStatus::COUNTED->value);
            }

            $normalized = $this->normalizeCounts($counts);
            if ($normalized === []) {
                throw new InventoryException('盘点数量不能为空');
            }

            foreach ($normalized as $itemId => $countedQty) {
                $item = StocktakeItem::query()
                    ->where('stocktake_id', $stocktake->id)
                    ->whereKey($itemId)
                    ->first();

                if ($item === null) {
                    throw new InventoryException('盘点行不存在：'.$itemId);
                }

                $diffQty = round($countedQty - (float) $item->book_qty, 3);

                $item->forceFill([
                    'counted_qty' => $countedQty,
                    'diff_qty' => $diffQty,
                    'diff_amount' => $this->money($diffQty * (float) $item->unit_cost),
                    'adjusted' => false,
                ])->save();
            }

            $stocktake->forceFill([
                'status' => StocktakeStatus::COUNTED,
                'counted_at' => now(),
                'operator_id' => $operatorId ?? $stocktake->operator_id,
            ])->save();

            return $stocktake->refresh()->load('items');
        });
    }

    /**
     * 差异调账：counted → completed。差异写 adjust 流水并回写 stock_items。
     */
    public function adjust(Stocktake $stocktake, ?int $operatorId = null): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $operatorId): Stocktake {
            $stocktake = $this->lockStocktake($stocktake);
            $from = $this->status($stocktake);

            if (! $from->canTransitTo(StocktakeStatus::COMPLETED)) {
                throw InvalidStateTransitionException::for('盘点单', $from->value, StocktakeStatus::COMPLETED->value);
            }

            $diffItems = 0;
            $diffAmount = 0.0;

            $items = StocktakeItem::query()
                ->where('stocktake_id', $stocktake->id)
                ->where('adjusted', false)
                ->whereNotNull('counted_qty')
                ->get();

            foreach ($items as $item) {
                if (abs((float) $item->diff_qty) > 0.0005) {
                    $this->stock->adjust([
                        'warehouse_id' => $stocktake->warehouse_id,
                        'item_type' => $item->item_type,
                        'item_id' => $item->item_id,
                        'target_qty' => (float) $item->counted_qty,
                        'unit_cost' => (float) $item->unit_cost,
                        'biz_type' => BizType::ADJUST,
                        'biz_id' => $stocktake->stocktake_no,
                        'operator_id' => $operatorId,
                    ]);

                    $diffItems++;
                    $diffAmount += (float) $item->diff_amount;
                }

                $item->forceFill(['adjusted' => true])->save();
            }

            $stocktake->forceFill([
                'status' => StocktakeStatus::COMPLETED,
                'diff_items' => $diffItems,
                'diff_amount' => $this->money($diffAmount),
                'adjusted_by' => $operatorId,
                'completed_at' => now(),
            ])->save();

            return $stocktake->refresh()->load('items');
        });
    }

    /**
     * 取消盘点单：counting / counted → cancelled。
     */
    public function cancel(Stocktake $stocktake, ?int $operatorId = null): Stocktake
    {
        return DB::transaction(function () use ($stocktake, $operatorId): Stocktake {
            $stocktake = $this->lockStocktake($stocktake);
            $from = $this->status($stocktake);

            if (! $from->canTransitTo(StocktakeStatus::CANCELLED)) {
                throw InvalidStateTransitionException::for('盘点单', $from->value, StocktakeStatus::CANCELLED->value);
            }

            $stocktake->forceFill([
                'status' => StocktakeStatus::CANCELLED,
                'adjusted_by' => $operatorId,
                'completed_at' => now(),
            ])->save();

            return $stocktake->refresh();
        });
    }

    // ---------------------------------------------------------------------
    // 内部实现
    // ---------------------------------------------------------------------

    private function lockStocktake(Stocktake $stocktake): Stocktake
    {
        return Stocktake::query()->whereKey($stocktake->id)->lockForUpdate()->firstOrFail();
    }

    private function status(Stocktake $stocktake): StocktakeStatus
    {
        $status = $stocktake->status;

        return $status instanceof StocktakeStatus ? $status : StocktakeStatus::from((string) $status);
    }

    /**
     * @param  array<int|string, mixed>  $counts
     * @return array<int, float>
     */
    private function normalizeCounts(array $counts): array
    {
        $normalized = [];

        foreach ($counts as $key => $value) {
            if (is_array($value)) {
                $itemId = (int) ($value['stocktake_item_id'] ?? $value['id'] ?? 0);
                $countedQty = round((float) ($value['counted_qty'] ?? $value['qty'] ?? 0), 3);
            } else {
                $itemId = (int) $key;
                $countedQty = round((float) $value, 3);
            }

            if ($itemId <= 0) {
                throw new InventoryException('盘点行 id 非法');
            }

            if ($countedQty < 0) {
                throw new InventoryException('实盘数量不能为负：'.$itemId);
            }

            $normalized[$itemId] = $countedQty;
        }

        return $normalized;
    }

    private function generateNo(): string
    {
        do {
            $no = 'ST'.now()->format('YmdHis').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (DB::table('stocktakes')->where('stocktake_no', $no)->exists());

        return $no;
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }
}
