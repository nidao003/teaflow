<?php

namespace Tests\Feature\Domain\Store;

use App\Domain\Store\Models\Region;
use App\Domain\Store\Services\RegionService;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 区域/门店域测试基类：RefreshDatabase + phpunit.xml 固定的 sqlite :memory:。
 *
 * 门店数据用 DB::table 直接造（stores 的 seller_id/title/area/address/phone 必填），
 * 不依赖既有 Store 控制器与 Seller 业务数据。
 */
abstract class StoreTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createStore(array $overrides = []): int
    {
        $now = now()->toDateTimeString();

        return (int) DB::table('stores')->insertGetId(array_merge([
            'seller_id' => 1,
            'title' => '测试门店-'.uniqid(),
            'area' => json_encode(['上海市', '静安区'], JSON_UNESCAPED_UNICODE),
            'address' => '南京西路 100 号',
            'phone' => '021-00000000',
            'type' => 'default',
            'is_open' => '1',
            'is_delivery' => '1',
            'user_id' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    protected function findStore(int $storeId): Store
    {
        return Store::query()->findOrFail($storeId);
    }

    protected function createRegion(string $code, ?Region $parent = null): Region
    {
        /** @var RegionService $regions */
        $regions = $this->app->make(RegionService::class);

        return $regions->create([
            'parent_id' => $parent?->getKey(),
            'code' => $code,
            'name' => '区域-'.$code,
        ]);
    }

    protected function at(string $datetime, string $timezone = 'Asia/Shanghai'): Carbon
    {
        return Carbon::parse($datetime, $timezone);
    }
}
