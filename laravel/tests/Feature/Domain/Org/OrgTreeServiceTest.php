<?php

namespace Tests\Feature\Domain\Org;

use App\Domain\Org\Exceptions\OrgTreeException;
use App\Domain\Org\Models\OrgUnit;
use App\Domain\Org\Services\OrgTreeService;

class OrgTreeServiceTest extends OrgTestCase
{
    private OrgTreeService $tree;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tree = app(OrgTreeService::class);
    }

    /**
     * @return array{company: OrgUnit, region: OrgUnit, store: OrgUnit}
     */
    private function makeTree(): array
    {
        $company = $this->tree->create([
            'type' => OrgUnit::TYPE_COMPANY,
            'code' => 'HQ',
            'name' => '总部',
        ]);

        $region = $this->tree->create([
            'parent_id' => $company->id,
            'type' => OrgUnit::TYPE_REGION,
            'code' => 'RG-EAST',
            'name' => '华东大区',
        ]);

        $store = $this->tree->create([
            'parent_id' => $region->id,
            'type' => OrgUnit::TYPE_STORE,
            'code' => 'ST-SH-001',
            'name' => '上海静安店',
        ]);

        return compact('company', 'region', 'store');
    }

    public function test_create_builds_path_and_level(): void
    {
        ['company' => $company, 'region' => $region, 'store' => $store] = $this->makeTree();

        $this->assertSame(1, $company->level);
        $this->assertSame($company->id.'/', $company->path);
        $this->assertNull($company->parent_id);

        $this->assertSame(2, $region->level);
        $this->assertSame($company->id.'/'.$region->id.'/', $region->path);

        $this->assertSame(3, $store->level);
        $this->assertSame($company->id.'/'.$region->id.'/'.$store->id.'/', $store->path);
        $this->assertSame(OrgUnit::STATUS_ACTIVE, $store->status);
    }

    public function test_parent_and_children_relations(): void
    {
        ['company' => $company, 'region' => $region, 'store' => $store] = $this->makeTree();

        $this->assertSame($company->id, $region->parent->id);
        $this->assertSame($region->id, $store->parent->id);
        $this->assertSame([$region->id], $company->children()->pluck('id')->all());
        $this->assertSame([$store->id], $region->children()->pluck('id')->all());
        $this->assertCount(0, $store->children()->get());
    }

    public function test_descendants_and_ancestors(): void
    {
        ['company' => $company, 'region' => $region, 'store' => $store] = $this->makeTree();

        $this->assertEqualsCanonicalizing(
            [$region->id, $store->id],
            $company->descendants()->pluck('id')->all()
        );
        $this->assertSame([$store->id], $region->descendants()->pluck('id')->all());
        $this->assertSame([], $store->descendants()->pluck('id')->all());

        // 祖先链由近到远
        $this->assertSame([$region->id, $company->id], $store->ancestors()->pluck('id')->all());
        $this->assertSame([$company->id], $region->ancestors()->pluck('id')->all());

        $this->assertEqualsCanonicalizing(
            [$region->id, $store->id],
            $this->tree->descendantIds($company)
        );
        $this->assertEqualsCanonicalizing(
            [$company->id, $region->id, $store->id],
            $this->tree->descendantIds($company, true)
        );
    }

    public function test_move_rewrites_subtree_path_and_level(): void
    {
        ['company' => $company, 'region' => $region, 'store' => $store] = $this->makeTree();

        $newCompany = $this->tree->create([
            'type' => OrgUnit::TYPE_COMPANY,
            'code' => 'HQ-2',
            'name' => '第二总部',
        ]);

        $moved = $this->tree->move($region, $newCompany);

        $this->assertSame($newCompany->id, $moved->parent_id);
        $this->assertSame(2, $moved->level);
        $this->assertSame($newCompany->id.'/'.$region->id.'/', $moved->path);

        $store->refresh();
        $this->assertSame(
            $newCompany->id.'/'.$region->id.'/'.$store->id.'/',
            $store->path
        );
        $this->assertSame(3, $store->level);

        // 原公司不再有任何后代；新公司的后代链正确
        $this->assertCount(0, $company->descendants()->get());
        $this->assertEqualsCanonicalizing(
            [$region->id, $store->id],
            $newCompany->descendants()->pluck('id')->all()
        );
        $this->assertSame([$region->id, $newCompany->id], $store->ancestors()->pluck('id')->all());
    }

    public function test_move_to_root_resets_level_and_path(): void
    {
        ['region' => $region, 'store' => $store] = $this->makeTree();

        $moved = $this->tree->move($region, null);

        $this->assertNull($moved->parent_id);
        $this->assertSame(1, $moved->level);
        $this->assertSame($region->id.'/', $moved->path);

        $store->refresh();
        $this->assertSame($region->id.'/'.$store->id.'/', $store->path);
        $this->assertSame(2, $store->level);
    }

    public function test_move_into_own_descendant_is_rejected(): void
    {
        ['region' => $region, 'store' => $store] = $this->makeTree();

        $this->expectException(OrgTreeException::class);

        $this->tree->move($region, $store);
    }

    public function test_move_into_itself_is_rejected(): void
    {
        ['region' => $region] = $this->makeTree();

        $this->expectException(OrgTreeException::class);

        $this->tree->move($region, $region);
    }

    public function test_failed_move_keeps_original_tree(): void
    {
        ['region' => $region, 'store' => $store] = $this->makeTree();
        $originalPath = $region->path;

        try {
            $this->tree->move($region, $store);
            $this->fail('移动成环时应当抛出异常');
        } catch (OrgTreeException) {
            // expected
        }

        $this->assertSame($originalPath, $region->fresh()->path);
        $this->assertSame($store->parent_id, $store->fresh()->parent_id);
    }

    public function test_create_with_invalid_type_is_rejected(): void
    {
        $this->expectException(OrgTreeException::class);

        $this->tree->create([
            'type' => 'warehouse',
            'code' => 'WH-01',
            'name' => '非法类型',
        ]);
    }

    public function test_of_type_scope_filters_units(): void
    {
        $this->makeTree();

        $this->assertSame(1, OrgUnit::query()->ofType(OrgUnit::TYPE_COMPANY)->count());
        $this->assertSame(1, OrgUnit::query()->ofType(OrgUnit::TYPE_REGION)->count());
        $this->assertSame(1, OrgUnit::query()->ofType(OrgUnit::TYPE_STORE)->count());
        $this->assertSame(3, OrgUnit::query()->ofType(OrgUnit::TYPE_COMPANY, OrgUnit::TYPE_STORE, OrgUnit::TYPE_REGION)->count());
    }
}
