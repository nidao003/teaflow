<?php

namespace Tests\Feature\Domain\Product;

use App\Domain\Product\Models\ProductSku;
use App\Domain\Product\Services\SkuService;
use InvalidArgumentException;

class SkuServiceTest extends ProductTestCase
{
    private SkuService $skuService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skuService = app(SkuService::class);
    }

    public function test_create_sku(): void
    {
        $productId = $this->createProduct();

        $sku = $this->skuService->create([
            'product_id' => $productId,
            'spec_key' => '大杯/半糖',
            'price' => 18.8,
            'market_price' => 22,
            'cost' => 5.25,
        ]);

        $this->assertSame($productId, $sku->product_id);
        $this->assertSame('大杯/半糖', $sku->spec_key);
        $this->assertSame('18.80', $sku->price);
        $this->assertSame('22.00', $sku->market_price);
        $this->assertSame('5.25', $sku->cost);
        $this->assertSame(ProductSku::STATUS_ACTIVE, $sku->status);
        $this->assertDatabaseHas('product_skus', [
            'id' => $sku->id,
            'product_id' => $productId,
            'spec_key' => '大杯/半糖',
        ]);
    }

    public function test_duplicate_spec_key_in_same_product_rejected(): void
    {
        $productId = $this->createProduct();
        $this->createSku($productId, '大杯/全糖');

        $this->expectException(InvalidArgumentException::class);

        $this->createSku($productId, '大杯/全糖');
    }

    public function test_same_spec_key_allowed_in_different_products(): void
    {
        $productA = $this->createProduct();
        $productB = $this->createProduct();

        $skuA = $this->createSku($productA, '大杯/全糖');
        $skuB = $this->createSku($productB, '大杯/全糖');

        $this->assertNotSame($skuA->id, $skuB->id);
    }

    public function test_update_sku(): void
    {
        $productId = $this->createProduct();
        $sku = $this->createSku($productId, '大杯/全糖');

        $updated = $this->skuService->update($sku->id, [
            'spec_key' => '大杯/半糖',
            'price' => 20.5,
            'market_price' => 25,
            'cost' => 6,
            'status' => ProductSku::STATUS_DISABLED,
        ]);

        $this->assertSame('大杯/半糖', $updated->spec_key);
        $this->assertSame('20.50', $updated->price);
        $this->assertSame('25.00', $updated->market_price);
        $this->assertSame('6.00', $updated->cost);
        $this->assertSame(ProductSku::STATUS_DISABLED, $updated->status);
        $this->assertDatabaseHas('product_skus', ['id' => $sku->id, 'spec_key' => '大杯/半糖', 'status' => -1]);
    }

    public function test_update_to_existing_spec_key_rejected(): void
    {
        $productId = $this->createProduct();
        $this->createSku($productId, '大杯/全糖');
        $second = $this->createSku($productId, '中杯/全糖');

        $this->expectException(InvalidArgumentException::class);

        $this->skuService->update($second->id, ['spec_key' => '大杯/全糖']);
    }

    public function test_update_invalid_price_rejected(): void
    {
        $productId = $this->createProduct();
        $sku = $this->createSku($productId);

        $this->expectException(InvalidArgumentException::class);

        $this->skuService->update($sku->id, ['price' => -1]);
    }

    public function test_list_filters_by_product_and_status(): void
    {
        $productA = $this->createProduct();
        $productB = $this->createProduct();

        $this->createSku($productA, '大杯/全糖');
        $this->createSku($productA, '中杯/全糖');
        $disabled = $this->createSku($productB, '大杯/无糖');
        $this->skuService->update($disabled->id, ['status' => ProductSku::STATUS_DISABLED]);

        $this->assertCount(2, $this->skuService->list(['product_id' => $productA]));
        $this->assertCount(1, $this->skuService->list(['product_id' => $productB]));
        $this->assertCount(1, $this->skuService->list(['status' => ProductSku::STATUS_DISABLED]));
        $this->assertCount(2, $this->skuService->list(['status' => ProductSku::STATUS_ACTIVE]));
    }

    public function test_update_missing_sku_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->skuService->update(9999, ['price' => 1]);
    }
}
