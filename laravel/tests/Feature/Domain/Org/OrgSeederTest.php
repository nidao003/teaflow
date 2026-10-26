<?php

namespace Tests\Feature\Domain\Org;

use App\Domain\Org\Models\OrgUnit;
use App\Models\User;
use Database\Seeders\OrgSeeder;
use Spatie\Permission\Models\Role;

class OrgSeederTest extends OrgTestCase
{
    public function test_seed_creates_company_regions_and_stores(): void
    {
        $this->seed(OrgSeeder::class);

        $this->assertSame(8, OrgUnit::query()->count());
        $this->assertSame(1, OrgUnit::query()->ofType(OrgUnit::TYPE_COMPANY)->count());
        $this->assertSame(2, OrgUnit::query()->ofType(OrgUnit::TYPE_REGION)->count());
        $this->assertSame(5, OrgUnit::query()->ofType(OrgUnit::TYPE_STORE)->count());

        // 树结构完整：每个非根节点都有父级，且 path 以父级 path 为前缀
        foreach (OrgUnit::query()->whereNotNull('parent_id')->get() as $unit) {
            $parent = $unit->parent;
            $this->assertNotNull($parent, "组织 {$unit->code} 的父级不存在");
            $this->assertStringStartsWith($parent->path, $unit->path);
            $this->assertSame($parent->level + 1, $unit->level);
        }

        $hq = OrgUnit::query()->where('code', 'HQ')->firstOrFail();
        $this->assertNull($hq->parent_id);
        $this->assertSame(1, $hq->level);
        $this->assertSame($hq->id.'/', $hq->path);

        // 门店都挂在区域下
        foreach (OrgUnit::query()->ofType(OrgUnit::TYPE_STORE)->get() as $store) {
            $this->assertSame(OrgUnit::TYPE_REGION, $store->parent->type);
        }
    }

    public function test_seed_is_idempotent(): void
    {
        $this->seed(OrgSeeder::class);
        $this->seed(OrgSeeder::class);

        $this->assertSame(8, OrgUnit::query()->count());
        $this->assertSame(8, OrgUnit::query()->distinct()->count('code'));
    }

    public function test_seed_creates_roles_and_permissions(): void
    {
        if (! class_exists(Role::class)) {
            $this->markTestSkipped('spatie/laravel-permission 未安装，跳过角色/权限测试');
        }

        $this->seed(OrgSeeder::class);

        $roles = Role::query()->whereIn('name', array_keys(OrgSeeder::ROLES))->get();
        $this->assertCount(count(OrgSeeder::ROLES), $roles);

        $platformAdmin = Role::findByName('platform_admin', OrgSeeder::GUARD);
        $this->assertCount(count(OrgSeeder::PERMISSIONS), $platformAdmin->permissions);

        $cashier = Role::findByName('cashier', OrgSeeder::GUARD);
        $this->assertEqualsCanonicalizing(
            ['order.view', 'pos.operate'],
            $cashier->permissions->pluck('name')->all()
        );

        $finance = Role::findByName('finance', OrgSeeder::GUARD);
        $this->assertFalse($finance->hasPermissionTo('org.manage'));
        $this->assertTrue($finance->hasPermissionTo('report.view'));
    }

    public function test_user_can_be_assigned_role_and_check_permission(): void
    {
        if (! class_exists(Role::class) || ! method_exists(User::class, 'assignRole')) {
            $this->markTestSkipped('spatie/laravel-permission 或 User 的 HasRoles 尚未就绪，跳过角色分配测试');
        }

        $this->seed(OrgSeeder::class);

        $store = OrgUnit::query()->where('code', 'ST-SH-001')->firstOrFail();

        /** @var User $user */
        $user = User::factory()->create();
        $user->forceFill([
            'org_unit_id' => $store->id,
            'data_scope' => 'store',
        ])->save();

        $user->assignRole('store_manager');
        $user->refresh();

        $this->assertTrue($user->hasRole('store_manager'));
        $this->assertTrue($user->hasPermissionTo('pos.operate'));
        $this->assertTrue($user->hasPermissionTo('order.refund'));
        $this->assertFalse($user->hasPermissionTo('finance.view'));
    }
}
