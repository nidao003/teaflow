<?php

namespace Tests\Feature\Domain\Store;

use App\Domain\Store\Exceptions\RegionException;
use App\Domain\Store\Models\Region;
use App\Domain\Store\Models\RegionStore;
use App\Domain\Store\Services\RegionService;
use Illuminate\Support\Facades\DB;

class RegionServiceTest extends StoreTestCase
{
    private RegionService $regions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->regions = $this->app->make(RegionService::class);
    }

    public function test_create_builds_materialized_path_and_level(): void
    {
        $root = $this->createRegion('RG-EAST');
        $city = $this->createRegion('RG-EAST-SH', $root);
        $district = $this->createRegion('RG-EAST-SH-JA', $city);

        $this->assertSame(1, $root->level);
        $this->assertSame($root->id.'/', $root->path);
        $this->assertNull($root->parent_id);

        $this->assertSame(2, $city->level);
        $this->assertSame($root->id.'/'.$city->id.'/', $city->path);
        $this->assertSame($root->id, $city->parent_id);

        $this->assertSame(3, $district->level);
        $this->assertSame($root->id.'/'.$city->id.'/'.$district->id.'/', $district->path);
        $this->assertSame(Region::STATUS_ACTIVE, $district->status);
    }

    public function test_parent_children_and_tree_queries(): void
    {
        $root = $this->createRegion('RG-EAST');
        $shanghai = $this->createRegion('RG-EAST-SH', $root);
        $zhejiang = $this->createRegion('RG-EAST-ZJ', $root);
        $jingan = $this->createRegion('RG-EAST-SH-JA', $shanghai);

        $this->assertSame([$shanghai->id, $zhejiang->id], $root->children()->pluck('id')->all());
        $this->assertSame($root->id, $shanghai->parent->id);

        $this->assertEqualsCanonicalizing(
            [$shanghai->id, $zhejiang->id, $jingan->id],
            $root->descendants()->pluck('id')->all()
        );
        $this->assertSame([$jingan->id], $shanghai->descendants()->pluck('id')->all());

        // 祖先链由近到远
        $this->assertSame([$shanghai->id, $root->id], $jingan->ancestors()->pluck('id')->all());
        $this->assertSame([$root->id], $shanghai->ancestors()->pluck('id')->all());

        $this->assertEqualsCanonicalizing(
            [$root->id, $shanghai->id, $zhejiang->id, $jingan->id],
            $this->regions->descendantIds($root, true)
        );
        $this->assertEqualsCanonicalizing(
            [$shanghai->id, $zhejiang->id, $jingan->id],
            $this->regions->descendantIds($root)
        );
    }

    public function test_move_rewrites_subtree_path_and_level(): void
    {
        $east = $this->createRegion('RG-EAST');
        $south = $this->createRegion('RG-SOUTH');
        $shanghai = $this->createRegion('RG-EAST-SH', $east);
        $jingan = $this->createRegion('RG-EAST-SH-JA', $shanghai);

        $moved = $this->regions->move($shanghai, $south);

        $this->assertSame($south->id, $moved->parent_id);
        $this->assertSame(2, $moved->level);
        $this->assertSame($south->id.'/'.$shanghai->id.'/', $moved->path);

        $jingan->refresh();
        $this->assertSame($south->id.'/'.$shanghai->id.'/'.$jingan->id.'/', $jingan->path);
        $this->assertSame(3, $jingan->level);

        $this->assertCount(0, $east->descendants()->get());
        $this->assertEqualsCanonicalizing(
            [$shanghai->id, $jingan->id],
            $south->descendants()->pluck('id')->all()
        );
        $this->assertSame([$shanghai->id, $south->id], $jingan->ancestors()->pluck('id')->all());
    }

    public function test_move_to_root_resets_level_and_path(): void
    {
        $east = $this->createRegion('RG-EAST');
        $shanghai = $this->createRegion('RG-EAST-SH', $east);
        $jingan = $this->createRegion('RG-EAST-SH-JA', $shanghai);

        $moved = $this->regions->move($shanghai, null);

        $this->assertNull($moved->parent_id);
        $this->assertSame(1, $moved->level);
        $this->assertSame($shanghai->id.'/', $moved->path);

        $jingan->refresh();
        $this->assertSame($shanghai->id.'/'.$jingan->id.'/', $jingan->path);
        $this->assertSame(2, $jingan->level);
    }

    public function test_move_into_own_descendant_is_rejected_and_tree_kept(): void
    {
        $root = $this->createRegion('RG-EAST');
        $city = $this->createRegion('RG-EAST-SH', $root);
        $district = $this->createRegion('RG-EAST-SH-JA', $city);
        $originalPath = $city->path;

        try {
            $this->regions->move($city, $district);
            $this->fail('移动成环时应当抛出 RegionException');
        } catch (RegionException) {
            // expected
        }

        $this->assertSame($originalPath, $city->fresh()->path);
        $this->assertSame($city->id, $district->fresh()->parent_id);
        $this->assertCount(0, $district->descendants()->get());
    }

    public function test_move_into_itself_is_rejected(): void
    {
        $root = $this->createRegion('RG-EAST');

        $this->expectException(RegionException::class);

        $this->regions->move($root, $root);
    }

    public function test_assign_store_updates_region_id_and_pivot(): void
    {
        $region = $this->createRegion('RG-EAST');
        $storeId = $this->createStore(['title' => '静安店', 'store_code' => 'SH001']);

        $pivot = $this->regions->assignStore($region, $storeId);

        $this->assertSame($region->id, $pivot->region_id);
        $this->assertSame($storeId, $pivot->store_id);
        $this->assertSame(1, RegionStore::query()->where('store_id', $storeId)->count());

        $row = DB::table('stores')->where('id', $storeId)->first();
        $this->assertSame($region->id, (int) $row->region_id);

        $this->assertSame($region->id, $this->regions->regionOfStore($storeId)?->getKey());
    }

    public function test_assign_store_moves_between_regions_keeping_single_pivot_row(): void
    {
        $east = $this->createRegion('RG-EAST');
        $south = $this->createRegion('RG-SOUTH');
        $storeId = $this->createStore();

        $this->regions->assignStore($east, $storeId);
        $this->regions->assignStore($south, $storeId);

        $this->assertSame(1, RegionStore::query()->where('store_id', $storeId)->count());
        $this->assertSame($south->id, RegionStore::query()->where('store_id', $storeId)->value('region_id'));
        $this->assertSame($south->id, (int) DB::table('stores')->where('id', $storeId)->value('region_id'));
    }

    public function test_assign_store_to_disabled_region_is_rejected(): void
    {
        $region = $this->createRegion('RG-EAST');
        $region->fill(['status' => Region::STATUS_DISABLED])->save();
        $storeId = $this->createStore();

        $this->expectException(RegionException::class);

        $this->regions->assignStore($region->refresh(), $storeId);
    }

    public function test_assign_unknown_store_is_rejected(): void
    {
        $region = $this->createRegion('RG-EAST');

        $this->expectException(RegionException::class);

        $this->regions->assignStore($region, 999999);
    }

    public function test_assign_soft_deleted_store_is_rejected(): void
    {
        $region = $this->createRegion('RG-EAST');
        $storeId = $this->createStore();
        $this->findStore($storeId)->delete();

        $this->expectException(RegionException::class);

        $this->regions->assignStore($region, $storeId);
    }
}
