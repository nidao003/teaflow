<?php

namespace Tests\Feature\Domain\Menu;

use App\Domain\Menu\Services\PackageService;
use App\Domain\Menu\Services\StoreMenuService;
use App\Domain\Product\Models\ProductSku;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 门店菜单/售罄/套餐域测试基座：RefreshDatabase（phpunit.xml 已固定 sqlite :memory:）。
 */
abstract class MenuTestCase extends TestCase
{
    use RefreshDatabase;

    protected StoreMenuService $menuService;

    protected PackageService $packageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->menuService = app(StoreMenuService::class);
        $this->packageService = app(PackageService::class);
    }

    protected function createSku(
        string $price = '18.00',
        int $status = ProductSku::STATUS_ACTIVE,
        ?string $specKey = null,
    ): ProductSku {
        return ProductSku::create([
            'product_id' => 1,
            'spec_key' => $specKey ?? '规格-'.uniqid(),
            'price' => $price,
            'market_price' => $price,
            'status' => $status,
        ])->refresh();
    }
}
