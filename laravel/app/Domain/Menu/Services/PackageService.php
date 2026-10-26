<?php

namespace App\Domain\Menu\Services;

use App\Domain\Menu\Models\Package;
use App\Domain\Menu\Models\PackageItem;
use App\Domain\Product\Models\ProductSku;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * 套餐服务：创建/更新/取价/展开组成/可用性。
 *
 * 套餐可用性 = 套餐在售 + 在售卖时间窗内 + 每个成员 SKU 在该门店当前可售。
 */
class PackageService
{
    private StoreMenuService $storeMenuService;

    public function __construct(?StoreMenuService $storeMenuService = null)
    {
        $this->storeMenuService = $storeMenuService ?? new StoreMenuService;
    }

    /**
     * @param  array{name?: string, price?: int|float|string, status?: string|null, start_at?: string|null, end_at?: string|null}  $attributes
     * @param  array<int, array{product_sku_id?: int|string, qty?: int|string}>  $items
     */
    public function create(array $attributes, array $items): Package
    {
        $name = trim((string) ($attributes['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('套餐名称不能为空');
        }

        $normalizedItems = $this->normalizeItems($items);
        $status = (string) ($attributes['status'] ?? Package::STATUS_ON);
        $this->assertStatus($status);

        $startAt = $this->nullableDateTime($attributes['start_at'] ?? null, 'start_at');
        $endAt = $this->nullableDateTime($attributes['end_at'] ?? null, 'end_at');
        if ($startAt !== null && $endAt !== null && $endAt->lt($startAt)) {
            throw new InvalidArgumentException('end_at 不能早于 start_at');
        }

        return DB::transaction(function () use ($attributes, $name, $status, $startAt, $endAt, $normalizedItems) {
            $package = Package::create([
                'name' => $name,
                'price' => $this->money($attributes['price'] ?? 0, 'price'),
                'status' => $status,
                'start_at' => $startAt?->toDateTimeString(),
                'end_at' => $endAt?->toDateTimeString(),
            ]);

            $this->replaceItems($package, $normalizedItems);

            return $package->refresh()->load('items');
        });
    }

    /**
     * 更新套餐基础信息；$items 非 null 时整体替换成员组成。
     *
     * @param  array{name?: string, price?: int|float|string, status?: string|null, start_at?: string|null, end_at?: string|null}  $attributes
     * @param  array<int, array{product_sku_id?: int|string, qty?: int|string}>|null  $items
     */
    public function update(int $packageId, array $attributes = [], ?array $items = null): Package
    {
        $package = $this->requirePackage($packageId);
        $normalizedItems = $items === null ? null : $this->normalizeItems($items);

        return DB::transaction(function () use ($package, $attributes, $normalizedItems) {
            if (array_key_exists('name', $attributes)) {
                $name = trim((string) $attributes['name']);
                if ($name === '') {
                    throw new InvalidArgumentException('套餐名称不能为空');
                }
                $package->name = $name;
            }
            if (array_key_exists('price', $attributes)) {
                $package->price = $this->money($attributes['price'], 'price');
            }
            if (array_key_exists('status', $attributes) && $attributes['status'] !== null) {
                $status = (string) $attributes['status'];
                $this->assertStatus($status);
                $package->status = $status;
            }
            if (array_key_exists('start_at', $attributes)) {
                $package->start_at = $this->nullableDateTime($attributes['start_at'], 'start_at');
            }
            if (array_key_exists('end_at', $attributes)) {
                $package->end_at = $this->nullableDateTime($attributes['end_at'], 'end_at');
            }
            if ($package->start_at !== null && $package->end_at !== null && $package->end_at->lt($package->start_at)) {
                throw new InvalidArgumentException('end_at 不能早于 start_at');
            }

            $package->save();

            if ($normalizedItems !== null) {
                $this->replaceItems($package, $normalizedItems);
            }

            return $package->refresh()->load('items');
        });
    }

    /**
     * 套餐价（decimal 字符串）。
     */
    public function price(int $packageId): string
    {
        return (string) $this->requirePackage($packageId)->price;
    }

    /**
     * 展开套餐组成：成员 SKU、数量、平台单价与行小计（用于展示/对账，套餐价以 price() 为准）。
     *
     * @return array{
     *     package: Package,
     *     items: array<int, array{product_sku_id: int, spec_key: string, qty: int, unit_price: string, line_price: string}>,
     *     members_total: string
     * }
     */
    public function expand(int $packageId): array
    {
        $package = $this->requirePackage($packageId)->load('items.sku');

        $items = [];
        $total = 0.0;

        foreach ($package->items as $item) {
            $qty = (int) $item->qty;
            $unitPrice = (float) ($item->sku?->price ?? 0);
            $linePrice = round($unitPrice * $qty, 2);
            $total += $linePrice;

            $items[] = [
                'product_sku_id' => (int) $item->product_sku_id,
                'spec_key' => (string) ($item->sku?->spec_key ?? ''),
                'qty' => $qty,
                'unit_price' => $this->formatMoney($unitPrice),
                'line_price' => $this->formatMoney($linePrice),
            ];
        }

        return [
            'package' => $package,
            'items' => $items,
            'members_total' => $this->formatMoney(round($total, 2)),
        ];
    }

    public function isAvailable(
        int $packageId,
        int $storeId,
        DateTimeInterface|string|null $at = null,
    ): bool {
        return $this->availability($packageId, $storeId, $at)['available'];
    }

    /**
     * @return array{available: bool, reason: string|null}
     */
    public function availability(
        int $packageId,
        int $storeId,
        DateTimeInterface|string|null $at = null,
    ): array {
        $package = $this->requirePackage($packageId);
        $moment = $this->resolveAt($at);

        if ($package->status !== Package::STATUS_ON) {
            return $this->unavailable('package_off');
        }
        if ($package->start_at !== null && $moment->lt($package->start_at)) {
            return $this->unavailable('not_started');
        }
        if ($package->end_at !== null && $moment->gt($package->end_at)) {
            return $this->unavailable('ended');
        }

        $items = PackageItem::query()->where('package_id', $package->id)->get();
        if ($items->isEmpty()) {
            return $this->unavailable('empty_package');
        }

        foreach ($items as $item) {
            if ((int) $item->qty <= 0) {
                return $this->unavailable('invalid_qty');
            }

            $member = $this->storeMenuService->availability($storeId, (int) $item->product_sku_id, $moment);
            if (! $member['available']) {
                return $this->unavailable('member_'.($member['reason'] ?? 'unavailable'));
            }
        }

        return ['available' => true, 'reason' => null];
    }

    public function find(int $packageId): ?Package
    {
        return Package::query()->find($packageId);
    }

    /**
     * @param  array<int, array{product_sku_id?: int|string, qty?: int|string}>  $items
     * @return array<int, array{product_sku_id: int, qty: int}>
     */
    private function normalizeItems(array $items): array
    {
        if ($items === []) {
            throw new InvalidArgumentException('套餐至少需要一个成员 SKU');
        }

        $normalized = [];
        $seen = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('套餐成员每行必须是数组');
            }

            $skuId = (int) ($item['product_sku_id'] ?? 0);
            if ($skuId <= 0) {
                throw new InvalidArgumentException('套餐成员的 product_sku_id 必须为正整数');
            }
            if (isset($seen[$skuId])) {
                throw new InvalidArgumentException("套餐成员 SKU {$skuId} 重复");
            }
            if (! ProductSku::query()->whereKey($skuId)->exists()) {
                throw new InvalidArgumentException("SKU {$skuId} 不存在");
            }
            $seen[$skuId] = true;

            $qty = (int) ($item['qty'] ?? 1);
            if ($qty <= 0) {
                throw new InvalidArgumentException("SKU {$skuId} 的 qty 必须为正整数");
            }

            $normalized[] = ['product_sku_id' => $skuId, 'qty' => $qty];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{product_sku_id: int, qty: int}>  $items
     */
    private function replaceItems(Package $package, array $items): void
    {
        PackageItem::query()->where('package_id', $package->id)->delete();

        foreach ($items as $item) {
            PackageItem::create([
                'package_id' => $package->id,
                'product_sku_id' => $item['product_sku_id'],
                'qty' => $item['qty'],
            ]);
        }
    }

    private function requirePackage(int $packageId): Package
    {
        $package = $this->find($packageId);
        if (! $package) {
            throw new InvalidArgumentException("套餐 {$packageId} 不存在");
        }

        return $package;
    }

    private function assertStatus(string $status): void
    {
        if (! in_array($status, Package::STATUSES, true)) {
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

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function nullableDateTime(mixed $value, string $field): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException("{$field} 必须是日期时间字符串");
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            throw new InvalidArgumentException("{$field} 不是合法日期时间：「{$value}」");
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
     * @return array{available: false, reason: string}
     */
    private function unavailable(string $reason): array
    {
        return ['available' => false, 'reason' => $reason];
    }
}
