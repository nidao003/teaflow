<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Inventory\Enums\BizType;
use App\Domain\Inventory\Enums\ItemType;
use App\Domain\Inventory\Enums\LedgerDirection;
use App\Domain\Inventory\Exceptions\ConcurrentStockUpdateException;
use App\Domain\Inventory\Exceptions\InsufficientStockException;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\Batch;
use App\Domain\Inventory\Models\StockItem;
use App\Domain\Inventory\Models\StockLedger;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Models\Wastage;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * 库存服务：所有库存变动的唯一入口。
 *
 * 约定：
 * - 所有写操作在事务内完成；对 stock_items 行 lockForUpdate，并用 version 做乐观锁，
 *   版本冲突抛 ConcurrentStockUpdateException 后对整体事务做有界重试；
 * - 可用量 = qty - locked_qty，不足直接拒绝（不允许负库存）；
 * - 每次变动写 stock_ledger（含 balance_after），再更新 stock_items.qty；
 * - 入库用加权平均成本；出库按批次 FIFO（先到期先出），无批次则回落到平均成本。
 */
class StockService
{
    /** 乐观锁冲突时的最大事务重试次数。 */
    protected int $maxAttempts = 3;

    /**
     * 入库：增加库存、重算加权平均成本、可选登记批次，并写一条 in 流水。
     *
     * @param  array{
     *     warehouse_id:int, item_type?:string|ItemType, item_id:int, qty:float|int|string,
     *     unit?:string, unit_cost?:float, biz_type?:string|BizType, biz_id?:string|int|null,
     *     operator_id?:int|null, batch_no?:string, production_date?:string,
     *     expiry_date?:string, supplier_id?:int, purchase_order_id?:int
     * }  $data
     */
    public function receive(array $data): StockLedger
    {
        return $this->transactional(fn (): StockLedger => $this->receiveOnce($data));
    }

    /**
     * 出库：FIFO 扣减批次并写 out 流水（可能跨多个批次，返回多条流水）。
     *
     * @param  array{
     *     warehouse_id:int, item_type?:string|ItemType, item_id:int, qty:float|int|string,
     *     biz_type?:string|BizType, biz_id?:string|int|null, operator_id?:int|null, batch_id?:int
     * }  $data
     * @return StockLedger[]
     */
    public function issue(array $data): array
    {
        return $this->transactional(fn (): array => $this->issueOnce($data));
    }

    /**
     * 出库别名（语义化：领用/消耗）。
     *
     * @param  array<string, mixed>  $data
     * @return StockLedger[]
     */
    public function consume(array $data): array
    {
        $data['biz_type'] ??= BizType::CONSUME->value;

        return $this->issue($data);
    }

    /**
     * 调拨：源仓出库 + 目标仓入库，同一事务；批次信息随流水带过去。
     *
     * @param  array{
     *     from_warehouse_id:int, to_warehouse_id:int, item_type?:string|ItemType, item_id:int,
     *     qty:float|int|string, biz_id?:string|int|null, operator_id?:int|null, unit?:string
     * }  $data
     * @return array{out: StockLedger[], in: StockLedger[]}
     */
    public function transfer(array $data): array
    {
        return $this->transactional(function () use ($data): array {
            $fromWarehouseId = $this->warehouseId(['warehouse_id' => $data['from_warehouse_id'] ?? null]);
            $toWarehouseId = $this->warehouseId(['warehouse_id' => $data['to_warehouse_id'] ?? null]);

            if ($fromWarehouseId === $toWarehouseId) {
                throw new InventoryException('调出仓库与调入仓库不能相同');
            }

            $outLedgers = $this->issueOnce([
                'warehouse_id' => $fromWarehouseId,
                'item_type' => $data['item_type'] ?? null,
                'item_id' => $data['item_id'] ?? null,
                'qty' => $data['qty'] ?? null,
                'biz_type' => BizType::TRANSFER,
                'biz_id' => $data['biz_id'] ?? null,
                'operator_id' => $data['operator_id'] ?? null,
            ]);

            $inLedgers = [];
            foreach ($outLedgers as $ledger) {
                $payload = [
                    'warehouse_id' => $toWarehouseId,
                    'item_type' => ItemType::fromValue($ledger->item_type),
                    'item_id' => $ledger->item_id,
                    'unit' => $data['unit'] ?? 'unit',
                    'qty' => (float) $ledger->qty,
                    'unit_cost' => (float) $ledger->unit_cost,
                    'biz_type' => BizType::TRANSFER,
                    'biz_id' => $data['biz_id'] ?? null,
                    'operator_id' => $data['operator_id'] ?? null,
                ];

                if ($ledger->batch_id !== null) {
                    $batch = Batch::whereKey($ledger->batch_id)->first();
                    if ($batch !== null) {
                        $payload['batch_no'] = $batch->batch_no;
                        $payload['production_date'] = $batch->production_date?->toDateString();
                        $payload['expiry_date'] = $batch->expiry_date?->toDateString();
                    }
                }

                $inLedgers[] = $this->receiveOnce($payload);
            }

            return ['out' => $outLedgers, 'in' => $inLedgers];
        });
    }

    /**
     * 调账：把库存调整到目标数量（或按 delta 增减），差异写 adjust 流水。
     * 返回 null 表示账面与目标一致、无需写流水。
     *
     * @param  array{
     *     warehouse_id:int, item_type?:string|ItemType, item_id:int, target_qty?:float|int|string,
     *     delta?:float|int|string, unit_cost?:float|int|string, biz_type?:string|BizType,
     *     biz_id?:string|int|null, operator_id?:int|null
     * }  $data
     */
    public function adjust(array $data): ?StockLedger
    {
        return $this->transactional(function () use ($data): ?StockLedger {
            $warehouseId = $this->warehouseId($data);
            $itemType = ItemType::fromValue($data['item_type'] ?? null);
            $itemId = $this->itemId($data);
            $bizType = BizType::fromValue($data['biz_type'] ?? BizType::ADJUST->value);

            $stock = $this->lockStockItem($warehouseId, $itemType, $itemId, (string) ($data['unit'] ?? 'unit'));
            $current = $this->roundQty($stock->qty);

            if (array_key_exists('target_qty', $data)) {
                $target = $this->roundQty($data['target_qty']);
            } else {
                $target = $this->roundQty($current + $this->roundQty($data['delta'] ?? 0));
            }

            if ($target < 0) {
                throw new InsufficientStockException(
                    $warehouseId,
                    $itemType->value,
                    $itemId,
                    abs($target),
                    $current,
                    '调账后库存不能为负'
                );
            }

            $delta = $this->roundQty($target - $current);
            if (abs($delta) < 0.0005) {
                return null;
            }

            if ($delta > 0) {
                if (array_key_exists('unit_cost', $data)) {
                    $unitCost = $this->money($data['unit_cost']);
                    $newAvg = $this->weightedAverageCost($current, (float) $stock->avg_cost, $delta, $unitCost);
                    $this->applyStockMutation($stock, ['qty' => $target, 'avg_cost' => $newAvg]);
                } else {
                    $unitCost = $this->money($stock->avg_cost);
                    $this->applyStockMutation($stock, ['qty' => $target]);
                }
            } else {
                $unitCost = $this->money($stock->avg_cost);
                $this->applyStockMutation($stock, ['qty' => $target]);
            }

            return $this->writeLedger(
                $data,
                $warehouseId,
                $itemType,
                $itemId,
                $delta > 0 ? LedgerDirection::IN : LedgerDirection::OUT,
                $bizType,
                abs($delta),
                $unitCost,
                null,
                $target
            );
        });
    }

    /**
     * 报损：按 FIFO 出库（biz_type = waste），同时落 wastages 记录。
     *
     * @param  array{
     *     warehouse_id:int, item_type?:string|ItemType, item_id:int, qty:float|int|string,
     *     batch_id?:int, reason?:string, operator_id?:int|null, biz_id?:string|int|null
     * }  $data
     */
    public function waste(array $data): Wastage
    {
        return $this->transactional(function () use ($data): Wastage {
            $warehouseId = $this->warehouseId($data);
            $itemType = ItemType::fromValue($data['item_type'] ?? null);
            $itemId = $this->itemId($data);
            $qty = $this->positiveQty($data['qty'] ?? null);

            $ledgers = $this->issueOnce(array_merge($data, [
                'biz_type' => BizType::WASTE->value,
            ]));

            $amount = $this->money(array_sum(array_map(
                static fn (StockLedger $ledger): float => (float) $ledger->amount,
                $ledgers
            )));
            $unitCost = $qty > 0 ? $this->money($amount / $qty) : 0.0;

            return Wastage::create([
                'warehouse_id' => $warehouseId,
                'item_type' => $itemType,
                'item_id' => $itemId,
                'batch_id' => $ledgers[0]->batch_id ?? null,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'amount' => $amount,
                'reason' => $data['reason'] ?? null,
                'operator_id' => $data['operator_id'] ?? null,
                'wasted_at' => now(),
            ]);
        });
    }

    /**
     * 查询某仓库某物料的库存余额。
     */
    public function findStock(int $warehouseId, ItemType|string $itemType, int $itemId): ?StockItem
    {
        return StockItem::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_type', ItemType::fromValue($itemType)->value)
            ->where('item_id', $itemId)
            ->first();
    }

    // ---------------------------------------------------------------------
    // 内部实现
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $data
     */
    private function receiveOnce(array $data): StockLedger
    {
        $warehouseId = $this->warehouseId($data);
        $itemType = ItemType::fromValue($data['item_type'] ?? null);
        $itemId = $this->itemId($data);
        $qty = $this->positiveQty($data['qty'] ?? null);
        $unit = (string) ($data['unit'] ?? 'unit');
        $unitCost = $this->money($data['unit_cost'] ?? 0);
        $bizType = BizType::fromValue($data['biz_type'] ?? BizType::RECEIPT->value);

        $stock = $this->lockStockItem($warehouseId, $itemType, $itemId, $unit);

        $oldQty = $this->roundQty($stock->qty);
        $newQty = $this->roundQty($oldQty + $qty);
        $newAvgCost = $this->weightedAverageCost($oldQty, (float) $stock->avg_cost, $qty, $unitCost);

        $this->applyStockMutation($stock, ['qty' => $newQty, 'avg_cost' => $newAvgCost]);

        $batch = null;
        if ($this->hasBatchData($data)) {
            $batch = $this->upsertBatch(
                $warehouseId,
                $itemType,
                $itemId,
                $this->batchNo($data),
                $data,
                $qty,
                $unitCost
            );
        }

        return $this->writeLedger(
            $data,
            $warehouseId,
            $itemType,
            $itemId,
            LedgerDirection::IN,
            $bizType,
            $qty,
            $unitCost,
            $batch?->id,
            $newQty
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return StockLedger[]
     */
    private function issueOnce(array $data): array
    {
        $warehouseId = $this->warehouseId($data);
        $itemType = ItemType::fromValue($data['item_type'] ?? null);
        $itemId = $this->itemId($data);
        $qty = $this->positiveQty($data['qty'] ?? null);
        $bizType = BizType::fromValue($data['biz_type'] ?? BizType::CONSUME->value);

        $stock = $this->lockStockItem($warehouseId, $itemType, $itemId, (string) ($data['unit'] ?? 'unit'));

        $available = $this->roundQty((float) $stock->qty - (float) $stock->locked_qty);
        if ($qty > $available + 0.0001) {
            throw new InsufficientStockException($warehouseId, $itemType->value, $itemId, $qty, $available);
        }

        $avgCost = $this->money($stock->avg_cost);
        $balance = $this->roundQty($stock->qty);
        $remaining = $qty;
        $batchId = isset($data['batch_id']) && $data['batch_id'] !== null ? (int) $data['batch_id'] : null;

        $batchQuery = Batch::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_type', $itemType->value)
            ->where('item_id', $itemId)
            ->where('qty', '>', 0);

        if ($batchId !== null) {
            $batchQuery->whereKey($batchId);
        }

        $batches = $batchQuery->lockForUpdate()->fifo()->get();

        if ($batchId !== null && $batches->isEmpty()) {
            throw new InsufficientStockException(
                $warehouseId,
                $itemType->value,
                $itemId,
                $qty,
                0,
                '指定批次无可用库存'
            );
        }

        $ledgers = [];

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $take = $this->roundQty(min($remaining, (float) $batch->qty));
            if ($take <= 0) {
                continue;
            }

            $batchRemaining = $this->roundQty((float) $batch->qty - $take);
            $batch->forceFill([
                'qty' => $batchRemaining,
                'status' => $batchRemaining <= 0 ? 'exhausted' : 'active',
            ])->save();

            $balance = $this->roundQty($balance - $take);
            $ledgers[] = $this->writeLedger(
                $data,
                $warehouseId,
                $itemType,
                $itemId,
                LedgerDirection::OUT,
                $bizType,
                $take,
                (float) $batch->cost,
                $batch->id,
                $balance
            );

            $remaining = $this->roundQty($remaining - $take);
        }

        if ($batchId !== null && $remaining > 0) {
            throw new InsufficientStockException(
                $warehouseId,
                $itemType->value,
                $itemId,
                $qty,
                $this->roundQty($qty - $remaining),
                '指定批次库存不足'
            );
        }

        if ($remaining > 0) {
            // 无批次（或批次数量不足）部分按平均成本出库
            $balance = $this->roundQty($balance - $remaining);
            $ledgers[] = $this->writeLedger(
                $data,
                $warehouseId,
                $itemType,
                $itemId,
                LedgerDirection::OUT,
                $bizType,
                $remaining,
                $avgCost,
                null,
                $balance
            );
        }

        if ($balance < -0.0001) {
            throw new InsufficientStockException($warehouseId, $itemType->value, $itemId, $qty, $available);
        }

        $this->applyStockMutation($stock, ['qty' => $balance]);

        return $ledgers;
    }

    /**
     * 加锁读取（不存在则创建）库存行。
     */
    protected function lockStockItem(int $warehouseId, ItemType $itemType, int $itemId, string $unit): StockItem
    {
        $query = StockItem::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_type', $itemType->value)
            ->where('item_id', $itemId);

        $stock = (clone $query)->lockForUpdate()->first();
        if ($stock !== null) {
            return $stock;
        }

        try {
            $stock = StockItem::create([
                'warehouse_id' => $warehouseId,
                'item_type' => $itemType,
                'item_id' => $itemId,
                'unit' => $unit,
                'qty' => 0,
                'locked_qty' => 0,
                'avg_cost' => 0,
                'version' => 0,
            ]);
        } catch (QueryException $exception) {
            // 并发首次创建命中唯一索引，改读已存在行
            $stock = (clone $query)->lockForUpdate()->first();
            if ($stock === null) {
                throw $exception;
            }

            return $stock;
        }

        return StockItem::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * 乐观锁写入：version 不匹配即视为并发冲突（由事务重试）。
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function applyStockMutation(StockItem $stock, array $attributes): StockItem
    {
        $currentVersion = (int) $stock->version;

        $affected = StockItem::query()
            ->whereKey($stock->id)
            ->where('version', $currentVersion)
            ->update(array_merge($attributes, [
                'version' => $currentVersion + 1,
                'updated_at' => now(),
            ]));

        if ($affected === 0) {
            throw ConcurrentStockUpdateException::forStockItem((int) $stock->id, $currentVersion);
        }

        $stock->forceFill(array_merge($attributes, ['version' => $currentVersion + 1]));
        $stock->syncOriginal();

        return $stock;
    }

    /**
     * 写库存流水（只追加）。
     *
     * @param  array<string, mixed>  $data
     */
    private function writeLedger(
        array $data,
        int $warehouseId,
        ItemType $itemType,
        int $itemId,
        LedgerDirection $direction,
        BizType $bizType,
        float $qty,
        float $unitCost,
        ?int $batchId,
        float $balanceAfter
    ): StockLedger {
        $unitCost = $this->money($unitCost);

        return StockLedger::create([
            'warehouse_id' => $warehouseId,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'direction' => $direction,
            'biz_type' => $bizType,
            'biz_id' => $this->bizId($data),
            'qty' => $this->roundQty($qty),
            'unit_cost' => $unitCost,
            'amount' => $this->money($qty * $unitCost),
            'batch_id' => $batchId,
            'balance_after' => $this->roundQty($balanceAfter),
            'operator_id' => isset($data['operator_id']) ? (int) $data['operator_id'] : null,
            'created_at' => now(),
        ]);
    }

    /**
     * 批次入库：同批次则累加数量并按加权平均重算批次成本。
     *
     * @param  array<string, mixed>  $data
     */
    private function upsertBatch(
        int $warehouseId,
        ItemType $itemType,
        int $itemId,
        string $batchNo,
        array $data,
        float $qty,
        float $unitCost
    ): Batch {
        $batch = Batch::query()
            ->where('warehouse_id', $warehouseId)
            ->where('item_type', $itemType->value)
            ->where('item_id', $itemId)
            ->where('batch_no', $batchNo)
            ->lockForUpdate()
            ->first();

        if ($batch !== null) {
            $oldQty = $this->roundQty($batch->qty);
            $newQty = $this->roundQty($oldQty + $qty);
            $newCost = $this->weightedAverageCost($oldQty, (float) $batch->cost, $qty, $unitCost);

            $batch->forceFill([
                'qty' => $newQty,
                'cost' => $newCost,
                'status' => 'active',
                'production_date' => $this->dateOrNull($data['production_date'] ?? null) ?? $batch->production_date?->toDateString(),
                'expiry_date' => $this->dateOrNull($data['expiry_date'] ?? null) ?? $batch->expiry_date?->toDateString(),
            ])->save();

            return $batch->refresh();
        }

        return Batch::create([
            'warehouse_id' => $warehouseId,
            'item_type' => $itemType,
            'item_id' => $itemId,
            'batch_no' => $batchNo,
            'production_date' => $this->dateOrNull($data['production_date'] ?? null),
            'expiry_date' => $this->dateOrNull($data['expiry_date'] ?? null),
            'qty' => $qty,
            'cost' => $unitCost,
            'status' => 'active',
            'supplier_id' => isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
            'purchase_order_id' => isset($data['purchase_order_id']) ? (int) $data['purchase_order_id'] : null,
        ]);
    }

    /**
     * 有界重试事务：命中乐观锁冲突时整段重跑。
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function transactional(callable $callback): mixed
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return DB::transaction($callback);
            } catch (ConcurrentStockUpdateException $exception) {
                if ($attempt >= $this->maxAttempts) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * 加权平均成本：金额保留 2 位小数。
     */
    private function weightedAverageCost(float $oldQty, float $oldAvgCost, float $inQty, float $inUnitCost): float
    {
        $totalQty = $oldQty + $inQty;

        if ($totalQty <= 0 || $oldQty <= 0) {
            return $this->money($inUnitCost);
        }

        return $this->money((($oldQty * $oldAvgCost) + ($inQty * $inUnitCost)) / $totalQty);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasBatchData(array $data): bool
    {
        return ! empty($data['batch_no'])
            || ! empty($data['production_date'])
            || ! empty($data['expiry_date']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function batchNo(array $data): string
    {
        if (! empty($data['batch_no'])) {
            return (string) $data['batch_no'];
        }

        // 只给了效期但没给批次号时自动生成（同日同物料可复用）
        return 'AUTO-'.now()->format('Ymd').'-'.$this->itemId($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function warehouseId(array $data): int
    {
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);

        if ($warehouseId <= 0) {
            throw new InventoryException('缺少 warehouse_id');
        }

        if (! Warehouse::query()->whereKey($warehouseId)->exists()) {
            throw new InventoryException('仓库不存在：'.$warehouseId);
        }

        return $warehouseId;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function itemId(array $data): int
    {
        $itemId = (int) ($data['item_id'] ?? 0);

        if ($itemId <= 0) {
            throw new InventoryException('缺少 item_id');
        }

        return $itemId;
    }

    private function positiveQty(mixed $qty): float
    {
        $value = $this->roundQty($qty);

        if ($value <= 0) {
            throw new InventoryException('数量必须大于 0');
        }

        return $value;
    }

    private function roundQty(mixed $value): float
    {
        return round((float) $value, 3);
    }

    private function money(mixed $value): float
    {
        return round((float) $value, 2);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bizId(array $data): ?string
    {
        $bizId = $data['biz_id'] ?? null;

        if ($bizId === null || $bizId === '') {
            return null;
        }

        return (string) $bizId;
    }

    private function dateOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return Carbon::parse((string) $value)->toDateString();
    }
}
