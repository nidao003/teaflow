<?php

namespace App\Domain\Product\Services;

use App\Domain\Product\Models\ProductSku;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * SKU 服务：只操作 product_skus，商品主数据仍由现有 products 表/模型负责。
 */
class SkuService
{
    public function create(array $data): ProductSku
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $specKey = trim((string) ($data['spec_key'] ?? ''));

        if ($productId <= 0) {
            throw new InvalidArgumentException('product_id 必须为正整数');
        }
        if ($specKey === '') {
            throw new InvalidArgumentException('spec_key 不能为空');
        }
        if (ProductSku::query()->where('product_id', $productId)->where('spec_key', $specKey)->exists()) {
            throw new InvalidArgumentException("商品 {$productId} 下规格「{$specKey}」已存在");
        }

        $status = array_key_exists('status', $data) ? (int) $data['status'] : ProductSku::STATUS_ACTIVE;
        $this->assertStatus($status);

        return ProductSku::create([
            'product_id' => $productId,
            'spec_key' => $specKey,
            'price' => $this->money($data['price'] ?? 0, 'price'),
            'market_price' => $this->money($data['market_price'] ?? 0, 'market_price'),
            'cost' => $this->money($data['cost'] ?? 0, 'cost'),
            'status' => $status,
        ])->refresh();
    }

    public function update(int $id, array $data): ProductSku
    {
        $sku = ProductSku::query()->find($id);
        if (! $sku) {
            throw new InvalidArgumentException("SKU {$id} 不存在");
        }

        if (array_key_exists('spec_key', $data)) {
            $specKey = trim((string) $data['spec_key']);
            if ($specKey === '') {
                throw new InvalidArgumentException('spec_key 不能为空');
            }
            $duplicated = ProductSku::query()
                ->where('product_id', $sku->product_id)
                ->where('spec_key', $specKey)
                ->where('id', '!=', $sku->id)
                ->exists();
            if ($duplicated) {
                throw new InvalidArgumentException("商品 {$sku->product_id} 下规格「{$specKey}」已存在");
            }
            $sku->spec_key = $specKey;
        }

        foreach (['price', 'market_price', 'cost'] as $field) {
            if (array_key_exists($field, $data)) {
                $sku->{$field} = $this->money($data[$field], $field);
            }
        }

        if (array_key_exists('status', $data)) {
            $status = (int) $data['status'];
            $this->assertStatus($status);
            $sku->status = $status;
        }

        $sku->save();

        return $sku->refresh();
    }

    /**
     * 列表查询：支持 product_id / status / spec_key 过滤。
     */
    public function list(array $filters = []): Collection
    {
        $query = ProductSku::query();

        if (! empty($filters['product_id'])) {
            $query->where('product_id', (int) $filters['product_id']);
        }
        if (array_key_exists('status', $filters) && $filters['status'] !== null && $filters['status'] !== '') {
            $query->where('status', (int) $filters['status']);
        }
        if (! empty($filters['spec_key'])) {
            $query->where('spec_key', (string) $filters['spec_key']);
        }

        return $query->orderBy('id')->get();
    }

    public function find(int $id): ?ProductSku
    {
        return ProductSku::query()->find($id);
    }

    private function assertStatus(int $status): void
    {
        if (! in_array($status, [ProductSku::STATUS_ACTIVE, ProductSku::STATUS_DISABLED], true)) {
            throw new InvalidArgumentException('status 仅支持 1（启用）或 -1（停用）');
        }
    }

    private function money(mixed $value, string $field): float
    {
        if (! is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException("{$field} 必须为非负数字");
        }

        return round((float) $value, 2);
    }
}
