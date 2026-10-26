<?php

namespace Tests\Feature\Domain\Store;

use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Exceptions\StoreNotOrderableException;
use App\Domain\Store\Models\Region;
use App\Domain\Store\Services\RegionService;
use App\Domain\Store\Services\StoreDirectory;
use App\Models\Store;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

class StoreDirectoryTest extends StoreTestCase
{
    private StoreDirectory $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = $this->app->make(StoreDirectory::class);
    }

    /**
     * @return array{root: Region, shanghai: Region, zhejiang: Region, south: Region}
     */
    private function regionTree(): array
    {
        $root = $this->createRegion('RG-EAST');
        $shanghai = $this->createRegion('RG-EAST-SH', $root);
        $zhejiang = $this->createRegion('RG-EAST-ZJ', $root);
        $south = $this->createRegion('RG-SOUTH');

        return compact('root', 'shanghai', 'zhejiang', 'south');
    }

    public function test_list_by_region_includes_descendants_by_default(): void
    {
        ['root' => $root, 'shanghai' => $shanghai, 'zhejiang' => $zhejiang, 'south' => $south] = $this->regionTree();

        $shanghaiStore = $this->createStore(['title' => '上海店', 'store_code' => 'SH001']);
        $zhejiangStore = $this->createStore(['title' => '浙江店', 'store_code' => 'ZJ001']);
        $southStore = $this->createStore(['title' => '广州店', 'store_code' => 'GD001']);

        $this->regions()->assignStore($shanghai, $shanghaiStore);
        $this->regions()->assignStore($zhejiang, $zhejiangStore);
        $this->regions()->assignStore($south, $southStore);

        $ids = $this->directory->listByRegion($root)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertEqualsCanonicalizing([$shanghaiStore, $zhejiangStore], $ids);

        $this->assertSame([], $this->directory->listByRegion($root, false)->pluck('id')->all());
        $this->assertSame([$shanghaiStore], $this->directory->listByRegion($shanghai)->pluck('id')->map(fn ($id): int => (int) $id)->all());
    }

    public function test_resolve_scope_filters_by_region_code_and_status(): void
    {
        ['root' => $root, 'shanghai' => $shanghai, 'south' => $south] = $this->regionTree();

        $openStore = $this->createStore(['store_code' => 'SH001', 'status' => StoreStatus::OPEN]);
        $closedStore = $this->createStore(['store_code' => 'SH002', 'status' => StoreStatus::CLOSED]);
        $southStore = $this->createStore(['store_code' => 'GD001', 'status' => StoreStatus::OPEN]);

        $this->regions()->assignStore($shanghai, $openStore);
        $this->regions()->assignStore($shanghai, $closedStore);
        $this->regions()->assignStore($south, $southStore);

        $ids = $this->directory->resolveScope(['region_code' => 'RG-EAST', 'open_only' => true])
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertSame([$openStore], $ids);

        $ids = $this->directory->resolveScope(['region_code' => 'RG-EAST', 'status' => StoreStatus::CLOSED])
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertSame([$closedStore], $ids);

        // 只查本区域、不含下级
        $ids = $this->directory->resolveScope(['region_id' => (int) $root->getKey(), 'include_descendants' => false])
            ->pluck('id')->all();
        $this->assertSame([], $ids);

        // 区域不存在时返回空集，而不是全表
        $this->assertSame([], $this->directory->resolveScope(['region_code' => 'NOT-EXIST'])->pluck('id')->all());

        // 按门店短码
        $ids = $this->directory->resolveScope(['store_code' => 'GD001'])->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertSame([$southStore], $ids);
    }

    public function test_resolve_scope_rejects_unknown_key(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->directory->resolveScope(['unknown_key' => 1]);
    }

    public function test_store_code_is_unique_but_nullable(): void
    {
        $this->createStore(['store_code' => 'SH001']);
        $this->createStore(['store_code' => null]);
        $this->createStore(['store_code' => null]);

        $this->expectException(QueryException::class);

        $this->createStore(['store_code' => 'SH001']);
    }

    public function test_parse_business_hours_with_default_and_day_overrides(): void
    {
        $hours = $this->directory->parseBusinessHours([
            'timezone' => 'Asia/Shanghai',
            'default' => [['09:00', '22:00']],
            'sun' => [],
        ]);

        $this->assertSame('Asia/Shanghai', $hours['timezone']);
        $this->assertTrue($hours['has_schedule']);
        $this->assertSame(540, $hours['days'][1][0]['open']);
        $this->assertSame(1320, $hours['days'][1][0]['close']);
        $this->assertSame([], $hours['days'][7]);

        $this->assertFalse($this->directory->parseBusinessHours(null)['has_schedule']);
        $this->assertFalse($this->directory->parseBusinessHours([])['has_schedule']);
    }

    public function test_parse_business_hours_supports_overnight_and_legacy_string(): void
    {
        $hours = $this->directory->parseBusinessHours('09:00-22:00,18:00-02:00');
        $this->assertTrue($hours['has_schedule']);
        $this->assertCount(2, $hours['days'][1]);
        $this->assertFalse($hours['days'][1][0]['overnight']);
        $this->assertTrue($hours['days'][1][1]['overnight']);
        $this->assertSame(1080, $hours['days'][1][1]['open']);
        $this->assertSame(1560, $hours['days'][1][1]['close']);

        $hours = $this->directory->parseBusinessHours(['mon' => [['18:00', '02:00']]]);
        $this->assertSame([], $hours['days'][2]);
        $this->assertTrue($hours['days'][1][0]['overnight']);
    }

    public function test_parse_business_hours_rejects_invalid_input(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->directory->parseBusinessHours(['funday' => [['09:00', '22:00']]]);
    }

    public function test_parse_business_hours_rejects_malformed_time(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->directory->parseBusinessHours(['default' => [['9:00', '25:00']]]);
    }

    public function test_open_state_within_and_outside_business_hours(): void
    {
        $storeId = $this->createStore([
            'status' => StoreStatus::OPEN,
            'business_hours' => json_encode([
                'timezone' => 'Asia/Shanghai',
                'default' => [['09:00', '22:00']],
            ]),
        ]);
        $store = $this->findStore($storeId);

        // 2026-10-26 是周一
        $this->assertSame(StoreDirectory::STATE_OPEN, $this->directory->openState($store, $this->at('2026-10-26 10:00:00')));
        $this->assertTrue($this->directory->isOpenNow($store, $this->at('2026-10-26 09:00:00')));
        $this->assertTrue($this->directory->canOrder($store, $this->at('2026-10-26 21:59:00')));
        $this->assertFalse($this->directory->canOrder($store, $this->at('2026-10-26 22:00:00')));
        $this->assertSame(
            StoreDirectory::STATE_OUTSIDE_BUSINESS_HOURS,
            $this->directory->openState($store, $this->at('2026-10-26 23:00:00'))
        );
    }

    public function test_open_state_respects_closed_and_suspended_status(): void
    {
        $closedId = $this->createStore(['status' => StoreStatus::CLOSED]);
        $suspendedId = $this->createStore(['status' => StoreStatus::SUSPENDED]);

        $this->assertSame(StoreDirectory::STATE_CLOSED, $this->directory->openState($this->findStore($closedId)));
        $this->assertSame(StoreDirectory::STATE_SUSPENDED, $this->directory->openState($this->findStore($suspendedId)));
        $this->assertFalse($this->directory->canOrder($this->findStore($closedId)));
    }

    public function test_no_business_hours_means_always_open_when_status_open(): void
    {
        $storeId = $this->createStore(['status' => StoreStatus::OPEN, 'business_hours' => null]);

        $this->assertTrue($this->directory->canOrder($this->findStore($storeId), $this->at('2026-10-26 03:00:00')));
    }

    public function test_overnight_business_hours_extend_to_next_day(): void
    {
        $storeId = $this->createStore([
            'status' => StoreStatus::OPEN,
            'business_hours' => json_encode([
                'timezone' => 'Asia/Shanghai',
                'mon' => [['18:00', '02:00']],
            ]),
        ]);
        $store = $this->findStore($storeId);

        $this->assertTrue($this->directory->canOrder($store, $this->at('2026-10-26 19:00:00'))); // 周一晚
        $this->assertTrue($this->directory->canOrder($store, $this->at('2026-10-27 01:00:00'))); // 周二凌晨延续
        $this->assertFalse($this->directory->canOrder($store, $this->at('2026-10-27 03:00:00'))); // 超时
        $this->assertFalse($this->directory->canOrder($store, $this->at('2026-10-27 20:00:00'))); // 周二不营业
    }

    public function test_soft_deleted_store_is_not_orderable(): void
    {
        $storeId = $this->createStore(['status' => StoreStatus::OPEN]);
        $this->findStore($storeId)->delete();

        $trashed = Store::withTrashed()->findOrFail($storeId);

        $this->assertSame(StoreDirectory::STATE_DELETED, $this->directory->openState($trashed));
        $this->assertFalse($this->directory->canOrder($trashed));
    }

    public function test_assert_orderable_throws_with_state(): void
    {
        $storeId = $this->createStore(['status' => StoreStatus::CLOSED]);

        $this->expectException(StoreNotOrderableException::class);

        $this->directory->assertOrderable($this->findStore($storeId));
    }

    public function test_assert_orderable_passes_when_open(): void
    {
        $storeId = $this->createStore(['status' => StoreStatus::OPEN]);

        $this->directory->assertOrderable($this->findStore($storeId), $this->at('2026-10-26 10:00:00'));

        $this->assertTrue(true);
    }

    public function test_summary_by_region_groups_by_status(): void
    {
        ['root' => $root, 'shanghai' => $shanghai, 'south' => $south] = $this->regionTree();

        $this->regions()->assignStore($shanghai, $this->createStore(['status' => StoreStatus::OPEN]));
        $this->regions()->assignStore($shanghai, $this->createStore(['status' => StoreStatus::CLOSED]));
        $this->regions()->assignStore($root, $this->createStore(['status' => StoreStatus::OPEN]));
        $this->regions()->assignStore($south, $this->createStore(['status' => StoreStatus::SUSPENDED]));

        $summary = $this->directory->summaryByRegion($root);

        $this->assertSame(3, $summary['store_count']);
        $this->assertSame(2, $summary['open_count']);
        $this->assertSame(1, $summary['closed_count']);
        $this->assertSame(0, $summary['suspended_count']);
        $this->assertSame('RG-EAST', $summary['region_code']);

        $summary = $this->directory->summaryByRegion($shanghai);
        $this->assertSame(2, $summary['store_count']);
        $this->assertSame(1, $summary['open_count']);
        $this->assertSame(1, $summary['closed_count']);

        // 不含下级时只统计直接挂在根区域下的门店（只有 1 家直接绑在 RG-EAST）
        $summary = $this->directory->summaryByRegion($root, false);
        $this->assertSame(1, $summary['store_count']);
        $this->assertSame(1, $summary['open_count']);
        $this->assertSame(0, $summary['closed_count']);
    }

    private function regions(): RegionService
    {
        return $this->app->make(RegionService::class);
    }
}
