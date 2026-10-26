<?php

namespace Database\Seeders;

use App\Domain\Menu\Models\Package;
use App\Domain\Menu\Models\StoreMenuItem;
use App\Domain\Menu\Services\PackageService;
use App\Domain\Menu\Services\StoreMenuService;
use App\Domain\Product\Models\ProductSku;
use App\Domain\Product\Services\SkuService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 门店菜单/售罄/套餐域演示数据：门店 1 的菜单（含门店价/时段/售罄）+ 1 个套餐。
 * 幂等：重复执行不会重复建 SKU/套餐，售罄演示只在尚未售罄时触发一次。
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        /** @var StoreMenuService $menuService */
        $menuService = app(StoreMenuService::class);

        /** @var PackageService $packageService */
        $packageService = app(PackageService::class);

        $skus = $this->ensureSkus();
        $storeId = 1;

        // 门店价覆盖平台价
        $menuService->publish($storeId, $skus[0]->id, ['price' => 16.00]);

        // 时段可售
        $menuService->publish($storeId, $skus[1]->id, [
            'price' => 15.00,
            'available_from' => '08:00',
            'available_to' => '22:00',
        ]);

        // 售罄演示（仅首次）
        $menuService->publish($storeId, $skus[2]->id, ['price' => 18.00]);
        $thirdItem = StoreMenuItem::query()
            ->where('store_id', $storeId)
            ->where('product_sku_id', $skus[2]->id)
            ->first();

        if ($thirdItem !== null && ! $thirdItem->sold_out) {
            $menuService->setSoldOut($storeId, $skus[2]->id, null, 'MenuSeeder 演示售罄');
        }

        if (! Package::query()->where('name', '双人下午茶套餐')->exists()) {
            $packageService->create([
                'name' => '双人下午茶套餐',
                'price' => 25.00,
                'status' => Package::STATUS_ON,
            ], [
                ['product_sku_id' => $skus[0]->id, 'qty' => 1],
                ['product_sku_id' => $skus[1]->id, 'qty' => 1],
            ]);
        }
    }

    /**
     * 确保有 3 个可演示的 SKU：优先复用已有 SKU，其次基于已有商品或新建商品补齐。
     *
     * @return array<int, ProductSku>
     */
    private function ensureSkus(): array
    {
        $skus = ProductSku::query()
            ->where('status', ProductSku::STATUS_ACTIVE)
            ->orderBy('id')
            ->limit(3)
            ->get()
            ->all();

        if (count($skus) >= 3) {
            return array_slice($skus, 0, 3);
        }

        $productId = (int) (DB::table('products')->orderBy('id')->value('id') ?? 0);
        if ($productId <= 0) {
            $now = now()->toDateTimeString();
            $productId = DB::table('products')->insertGetId([
                'title' => 'MenuSeeder 演示商品',
                'content' => '门店菜单/套餐演示用商品',
                'specs' => json_encode([['type' => '默认', 'price' => 1800, 'market_price' => 2200]]),
                'image' => 'https://example.test/menu-demo.png',
                'status' => 1,
                'type_id' => 0,
                'user_id' => 0,
                'sort' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        /** @var SkuService $skuService */
        $skuService = app(SkuService::class);

        $seenIds = array_map(fn (ProductSku $sku): int => (int) $sku->id, $skus);
        $specs = [
            ['spec_key' => '大杯/全糖', 'price' => 18.00],
            ['spec_key' => '中杯/半糖', 'price' => 15.00],
            ['spec_key' => '大杯/去冰', 'price' => 18.00],
        ];

        foreach ($specs as $spec) {
            if (count($skus) >= 3) {
                break;
            }

            $sku = ProductSku::query()
                ->where('product_id', $productId)
                ->where('spec_key', $spec['spec_key'])
                ->first();

            if (! $sku) {
                $sku = $skuService->create([
                    'product_id' => $productId,
                    'spec_key' => $spec['spec_key'],
                    'price' => $spec['price'],
                    'market_price' => $spec['price'],
                ]);
            }

            if (in_array((int) $sku->id, $seenIds, true)) {
                continue;
            }

            $seenIds[] = (int) $sku->id;
            $skus[] = $sku;
        }

        if (count($skus) < 3) {
            throw new RuntimeException('MenuSeeder 无法准备足够的演示 SKU');
        }

        return array_slice($skus, 0, 3);
    }
}
