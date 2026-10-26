<?php

namespace App\Domain\Menu\Services;

use App\Domain\Menu\Exceptions\MenuItemNotPublishedException;
use App\Domain\Menu\Models\SoldOutLog;
use App\Domain\Menu\Models\StoreMenuItem;
use App\Domain\Product\Models\ProductSku;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Throwable;

/**
 * 门店菜单服务：门店发布/门店价覆盖/上下架/售罄/时段可售。
 *
 * 可售判定顺序：门店未发布 → 平台 SKU 停用 → 门店下架 → 售罄 → 不在可售时段。
 * 时段支持跨天（available_from > available_to 表示从前一天延续到次日）。
 */
class StoreMenuService
{
    private SoldOutService $soldOutService;

    public function __construct(?SoldOutService $soldOutService = null)
    {
        $this->soldOutService = $soldOutService ?? new SoldOutService;
    }

    /**
     * 发布/更新门店菜单项（按 store_id + product_sku_id 幂等）。
     * 未指定 price 时默认取平台价；未指定 status 时新记录默认 on。
     *
     * @param  array{price?: int|float|string|null, status?: string|null, available_from?: string|null, available_to?: string|null}  $options
     */
    public function publish(int $storeId, int $productSkuId, array $options = []): StoreMenuItem
    {
        $this->assertStoreId($storeId);
        $sku = $this->requireSku($productSkuId);

        $item = StoreMenuItem::query()
            ->where('store_id', $storeId)
            ->where('product_sku_id', $productSkuId)
            ->first();

        $isNew = $item === null;
        $item ??= new StoreMenuItem([
            'store_id' => $storeId,
            'product_sku_id' => $productSkuId,
        ]);

        if (array_key_exists('price', $options) && $options['price'] !== null && $options['price'] !== '') {
            $item->price = $this->money($options['price'], 'price');
        } elseif ($isNew) {
            $item->price = $this->money($sku->price, 'price');
        }

        if (array_key_exists('status', $options) && $options['status'] !== null) {
            $status = (string) $options['status'];
            $this->assertStatus($status);
            $item->status = $status;
        } elseif ($isNew) {
            $item->status = StoreMenuItem::STATUS_ON;
        }

        foreach (['available_from', 'available_to'] as $field) {
            if (array_key_exists($field, $options)) {
                $item->{$field} = $this->normalizeTime($options[$field], $field);
            }
        }

        if ($isNew) {
            $item->sold_out = false;
            $item->sold_out_at = null;
        }

        $item->save();

        return $item->refresh();
    }

    /**
     * 门店价覆盖：更新门店菜单项价格，不影响其它门店与平台价。
     */
    public function updatePrice(int $storeId, int $productSkuId, int|float|string $price): StoreMenuItem
    {
        $item = $this->requireItem($storeId, $productSkuId);
        $item->price = $this->money($price, 'price');
        $item->save();

        return $item->refresh();
    }

    /**
     * 上下架：$status 为空时在当前状态间切换。
     */
    public function toggle(int $storeId, int $productSkuId, ?string $status = null): StoreMenuItem
    {
        $item = $this->requireItem($storeId, $productSkuId);

        $target = $status ?? ($item->status === StoreMenuItem::STATUS_ON
            ? StoreMenuItem::STATUS_OFF
            : StoreMenuItem::STATUS_ON);

        $this->assertStatus($target);

        $item->status = $target;
        $item->save();

        return $item->refresh();
    }

    /**
     * 标记售罄（幂等：已是售罄状态时不再重复写日志）。
     */
    public function setSoldOut(
        int $storeId,
        int $productSkuId,
        ?int $operatorId = null,
        ?string $note = null,
    ): StoreMenuItem {
        $item = $this->requireItem($storeId, $productSkuId);

        if ($item->sold_out) {
            return $item->refresh();
        }

        $item->sold_out = true;
        $item->sold_out_at = Carbon::now();
        $item->save();

        $this->soldOutService->record($storeId, $productSkuId, SoldOutLog::ACTION_SOLD_OUT, $operatorId, $note);

        return $item->refresh();
    }

    /**
     * 恢复售罄（幂等）。
     */
    public function restore(
        int $storeId,
        int $productSkuId,
        ?int $operatorId = null,
        ?string $note = null,
    ): StoreMenuItem {
        $item = $this->requireItem($storeId, $productSkuId);

        if (! $item->sold_out) {
            return $item->refresh();
        }

        $item->sold_out = false;
        $item->sold_out_at = null;
        $item->save();

        $this->soldOutService->record($storeId, $productSkuId, SoldOutLog::ACTION_RESTORED, $operatorId, $note);

        return $item->refresh();
    }

    public function isAvailable(
        int $storeId,
        int $productSkuId,
        DateTimeInterface|string|null $at = null,
    ): bool {
        return $this->availability($storeId, $productSkuId, $at)['available'];
    }

    /**
     * @return array{available: bool, reason: string|null}
     */
    public function availability(
        int $storeId,
        int $productSkuId,
        DateTimeInterface|string|null $at = null,
    ): array {
        $moment = $this->resolveAt($at);

        $item = StoreMenuItem::query()
            ->where('store_id', $storeId)
            ->where('product_sku_id', $productSkuId)
            ->first();

        if (! $item) {
            return ['available' => false, 'reason' => 'not_published'];
        }

        $sku = ProductSku::query()->find($productSkuId);
        if (! $sku) {
            return ['available' => false, 'reason' => 'sku_missing'];
        }
        if ((int) $sku->status !== ProductSku::STATUS_ACTIVE) {
            return ['available' => false, 'reason' => 'sku_inactive'];
        }
        if ($item->status !== StoreMenuItem::STATUS_ON) {
            return ['available' => false, 'reason' => 'off_shelf'];
        }
        if ($item->sold_out) {
            return ['available' => false, 'reason' => 'sold_out'];
        }
        if (! $this->withinTimeWindow($item, $moment)) {
            return ['available' => false, 'reason' => 'outside_time_window'];
        }

        return ['available' => true, 'reason' => null];
    }

    /**
     * 门店实际售价：有门店菜单项用门店价，否则回退平台价。
     */
    public function priceFor(int $storeId, int $productSkuId): string
    {
        $item = StoreMenuItem::query()
            ->where('store_id', $storeId)
            ->where('product_sku_id', $productSkuId)
            ->first();

        if ($item) {
            return (string) $item->price;
        }

        return (string) $this->requireSku($productSkuId)->price;
    }

    /**
     * 门店已发布菜单项（含 SKU，含下架/售罄，供后台维护用）。
     */
    public function itemsForStore(int $storeId): Collection
    {
        return StoreMenuItem::query()
            ->where('store_id', $storeId)
            ->with('sku')
            ->orderBy('id')
            ->get();
    }

    /**
     * 指定时点当前可售的门店菜单项。
     */
    public function availabilityList(int $storeId, DateTimeInterface|string|null $at = null): Collection
    {
        $moment = $this->resolveAt($at);

        return StoreMenuItem::query()
            ->where('store_id', $storeId)
            ->where('status', StoreMenuItem::STATUS_ON)
            ->where('sold_out', false)
            ->orderBy('id')
            ->get()
            ->filter(fn (StoreMenuItem $item): bool => $this->withinTimeWindow($item, $moment));
    }

    private function requireSku(int $productSkuId): ProductSku
    {
        if ($productSkuId <= 0) {
            throw new InvalidArgumentException('product_sku_id 必须为正整数');
        }

        $sku = ProductSku::query()->find($productSkuId);
        if (! $sku) {
            throw new InvalidArgumentException("SKU {$productSkuId} 不存在");
        }

        return $sku;
    }

    private function requireItem(int $storeId, int $productSkuId): StoreMenuItem
    {
        $this->assertStoreId($storeId);

        $item = StoreMenuItem::query()
            ->where('store_id', $storeId)
            ->where('product_sku_id', $productSkuId)
            ->first();

        if (! $item) {
            throw new MenuItemNotPublishedException("门店 {$storeId} 未发布 SKU {$productSkuId} 的菜单项");
        }

        return $item;
    }

    private function assertStoreId(int $storeId): void
    {
        if ($storeId <= 0) {
            throw new InvalidArgumentException('store_id 必须为正整数');
        }
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, StoreMenuItem::STATUSES, true)) {
            throw new InvalidArgumentException("status 仅支持 on/off，收到「{$status}」");
        }
    }

    private function money(int|float|string $value, string $field): float
    {
        if (! is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException("{$field} 必须为非负数字");
        }

        return round((float) $value, 2);
    }

    private function normalizeTime(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$field} 必须是 HH:MM 或 HH:MM:SS 格式时间");
        }

        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (Throwable) {
            throw new InvalidArgumentException("{$field} 不是合法时间：「{$value}」");
        }
    }

    private function resolveAt(DateTimeInterface|string|null $at): Carbon
    {
        if ($at === null) {
            return Carbon::now();
        }
        if ($at instanceof DateTimeInterface) {
            return Carbon::instance($at);
        }

        return Carbon::parse($at);
    }

    /**
     * 可售时段判定（门店时间）：两端为空表示不限；from > to 视为跨天时段。
     */
    private function withinTimeWindow(StoreMenuItem $item, Carbon $moment): bool
    {
        $from = $this->normalizeTime($item->available_from, 'available_from');
        $to = $this->normalizeTime($item->available_to, 'available_to');

        if ($from === null && $to === null) {
            return true;
        }

        $time = $moment->format('H:i:s');

        if ($from !== null && $to !== null) {
            if ($from === $to) {
                return true;
            }
            if ($from > $to) {
                return $time >= $from || $time <= $to;
            }
        }

        if ($from !== null && $time < $from) {
            return false;
        }
        if ($to !== null && $time > $to) {
            return false;
        }

        return true;
    }
}
