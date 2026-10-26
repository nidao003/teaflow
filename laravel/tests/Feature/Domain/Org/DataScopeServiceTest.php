<?php

namespace Tests\Feature\Domain\Org;

use App\Domain\Org\Models\OrgUnit;
use App\Domain\Org\Services\DataScopeService;
use App\Domain\Org\Services\OrgTreeService;
use App\Models\User;

class DataScopeServiceTest extends OrgTestCase
{
    private DataScopeService $dataScope;

    private OrgTreeService $tree;

    /** @var array<string, OrgUnit> */
    private array $units = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tree = app(OrgTreeService::class);
        $this->dataScope = app(DataScopeService::class);

        $this->units['hq'] = $this->tree->create([
            'type' => OrgUnit::TYPE_COMPANY, 'code' => 'HQ', 'name' => '总部',
        ]);
        $this->units['east'] = $this->tree->create([
            'parent_id' => $this->units['hq']->id, 'type' => OrgUnit::TYPE_REGION, 'code' => 'RG-EAST', 'name' => '华东大区',
        ]);
        $this->units['south'] = $this->tree->create([
            'parent_id' => $this->units['hq']->id, 'type' => OrgUnit::TYPE_REGION, 'code' => 'RG-SOUTH', 'name' => '华南大区',
        ]);
        $this->units['east1'] = $this->tree->create([
            'parent_id' => $this->units['east']->id, 'type' => OrgUnit::TYPE_STORE, 'code' => 'ST-SH-001', 'name' => '上海静安店',
        ]);
        $this->units['east2'] = $this->tree->create([
            'parent_id' => $this->units['east']->id, 'type' => OrgUnit::TYPE_STORE, 'code' => 'ST-SH-002', 'name' => '上海徐汇店',
        ]);
        $this->units['south1'] = $this->tree->create([
            'parent_id' => $this->units['south']->id, 'type' => OrgUnit::TYPE_STORE, 'code' => 'ST-GZ-001', 'name' => '广州天河店',
        ]);
    }

    private function makeUser(?OrgUnit $unit, ?string $scope): User
    {
        /** @var User $user */
        $user = User::factory()->create();

        // User 模型不允许改，测试里用 forceFill 设置组织字段（生产由管理端/后台加白名单）。
        $user->forceFill([
            'org_unit_id' => $unit?->id,
            'data_scope' => $scope,
        ])->save();

        return $user->refresh();
    }

    private function visibleIds(?User $user): array
    {
        return $this->dataScope->applyToOrgUnits($user)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function test_scope_all_sees_every_org_unit(): void
    {
        $user = $this->makeUser($this->units['hq'], DataScopeService::SCOPE_ALL);

        $this->assertCount(6, $this->visibleIds($user));
        $this->assertNull($this->dataScope->allowedOrgUnitIds($user));
    }

    public function test_scope_region_sees_own_region_and_its_stores_only(): void
    {
        $user = $this->makeUser($this->units['east'], DataScopeService::SCOPE_REGION);

        $this->assertEqualsCanonicalizing(
            [$this->units['east']->id, $this->units['east1']->id, $this->units['east2']->id],
            $this->visibleIds($user)
        );
    }

    public function test_scope_store_sees_own_store_only(): void
    {
        $user = $this->makeUser($this->units['east1'], DataScopeService::SCOPE_STORE);

        $this->assertSame([$this->units['east1']->id], $this->visibleIds($user));
    }

    public function test_scope_self_falls_back_to_own_org_unit_when_no_self_column(): void
    {
        $user = $this->makeUser($this->units['east1'], DataScopeService::SCOPE_SELF);

        $this->assertSame([$this->units['east1']->id], $this->visibleIds($user));
    }

    public function test_scope_self_with_self_column_filters_by_user_id(): void
    {
        $user = $this->makeUser($this->units['east1'], DataScopeService::SCOPE_SELF);

        $ids = $this->dataScope
            ->apply(User::query(), $user, 'org_unit_id', 'id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->assertSame([$user->id], $ids);
    }

    public function test_apply_uses_org_unit_column_on_business_tables(): void
    {
        $eastManager = $this->makeUser($this->units['east'], DataScopeService::SCOPE_REGION);
        $cashier = $this->makeUser($this->units['east1'], DataScopeService::SCOPE_STORE);
        $southCashier = $this->makeUser($this->units['south1'], DataScopeService::SCOPE_STORE);
        $hqAdmin = $this->makeUser($this->units['hq'], DataScopeService::SCOPE_ALL);

        // 区域经理可见华东下属两家店的用户，看不到华南
        $visible = $this->dataScope->apply(User::query(), $eastManager, 'org_unit_id')->pluck('id')->all();
        $this->assertContains($cashier->id, $visible);
        $this->assertNotContains($southCashier->id, $visible);

        // 门店店长只看本店
        $visible = $this->dataScope->apply(User::query(), $cashier, 'org_unit_id')->pluck('id')->all();
        $this->assertSame([$cashier->id], array_values(array_map('intval', $visible)));

        // 平台管理员不限范围
        $this->assertCount(4, $this->dataScope->apply(User::query(), $hqAdmin, 'org_unit_id')->get());
    }

    public function test_missing_org_unit_is_not_widened_to_all(): void
    {
        $user = $this->makeUser(null, DataScopeService::SCOPE_REGION);

        $this->assertSame([], $this->visibleIds($user));
        $this->assertSame([], $this->dataScope->allowedOrgUnitIds($user));
    }

    public function test_null_user_is_denied_by_default(): void
    {
        $this->assertSame([], $this->visibleIds(null));
        $this->assertSame([], $this->dataScope->allowedOrgUnitIds(null));
    }

    public function test_invalid_scope_degrades_to_self_and_never_full_table(): void
    {
        $user = $this->makeUser($this->units['east1'], 'bogus-scope');

        $this->assertSame(DataScopeService::SCOPE_SELF, $this->dataScope->scopeOf($user));
        $this->assertSame([$this->units['east1']->id], $this->visibleIds($user));
        $this->assertLessThan(6, count($this->visibleIds($user)));
    }

    public function test_null_scope_degrades_to_self(): void
    {
        $store = $this->units['east2'];

        // DB 列 NOT NULL default 'self'，这里用未持久化的模型模拟「历史数据/脏数据 scope 为空」
        $user = new User;
        $user->forceFill([
            'org_unit_id' => $store->id,
            'data_scope' => null,
        ]);

        $this->assertSame(DataScopeService::SCOPE_SELF, $this->dataScope->scopeOf($user));
        $this->assertSame([$store->id], $this->visibleIds($user));
        $this->assertSame(DataScopeService::SCOPE_SELF, $this->dataScope->scopeOf(null));
    }
}
