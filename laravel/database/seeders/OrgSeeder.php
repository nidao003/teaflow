<?php

namespace Database\Seeders;

use App\Domain\Org\Models\OrgUnit;
use App\Domain\Org\Services\OrgTreeService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * 组织/权限域基础数据：
 * - 组织单元：1 公司 + 2 区域 + 5 门店（幂等，按 code 判断）；
 * - 角色与权限点：使用 spatie/laravel-permission，权限按域命名（org.view / order.view / ...）。
 *
 * 若 spatie/laravel-permission 尚未安装，只跳过角色权限部分并告警，不影响组织树 seeding。
 */
class OrgSeeder extends Seeder
{
    /**
     * 组织树定义（父节点必须排在子节点之前）。
     *
     * @var array<int, array{code: string, name: string, type: string, parent_code: string|null, sort: int}>
     */
    public const ORG_UNITS = [
        ['code' => 'HQ', 'name' => '茶流科技（总部）', 'type' => OrgUnit::TYPE_COMPANY, 'parent_code' => null, 'sort' => 0],
        ['code' => 'RG-EAST', 'name' => '华东大区', 'type' => OrgUnit::TYPE_REGION, 'parent_code' => 'HQ', 'sort' => 10],
        ['code' => 'RG-SOUTH', 'name' => '华南大区', 'type' => OrgUnit::TYPE_REGION, 'parent_code' => 'HQ', 'sort' => 20],
        ['code' => 'ST-SH-001', 'name' => '上海静安店', 'type' => OrgUnit::TYPE_STORE, 'parent_code' => 'RG-EAST', 'sort' => 10],
        ['code' => 'ST-SH-002', 'name' => '上海徐汇店', 'type' => OrgUnit::TYPE_STORE, 'parent_code' => 'RG-EAST', 'sort' => 20],
        ['code' => 'ST-SH-003', 'name' => '上海浦东店', 'type' => OrgUnit::TYPE_STORE, 'parent_code' => 'RG-EAST', 'sort' => 30],
        ['code' => 'ST-GZ-001', 'name' => '广州天河店', 'type' => OrgUnit::TYPE_STORE, 'parent_code' => 'RG-SOUTH', 'sort' => 10],
        ['code' => 'ST-SZ-001', 'name' => '深圳南山店', 'type' => OrgUnit::TYPE_STORE, 'parent_code' => 'RG-SOUTH', 'sort' => 20],
    ];

    /**
     * 权限点：按域命名，格式 <domain>.<action>。
     *
     * @var array<int, string>
     */
    public const PERMISSIONS = [
        'org.view',
        'org.manage',
        'user.view',
        'user.manage',
        'store.view',
        'store.manage',
        'product.view',
        'product.manage',
        'order.view',
        'order.refund',
        'pos.operate',
        'inventory.view',
        'inventory.manage',
        'coupon.view',
        'report.view',
        'finance.view',
        'setting.manage',
    ];

    /**
     * 角色 -> 权限点。
     *
     * @var array<string, array<int, string>>
     */
    public const ROLES = [
        'platform_admin' => self::PERMISSIONS,
        'ops' => [
            'org.view',
            'user.view',
            'store.view',
            'product.view',
            'order.view',
            'pos.operate',
            'inventory.view',
            'coupon.view',
            'report.view',
        ],
        'region_manager' => [
            'org.view',
            'store.view',
            'product.view',
            'order.view',
            'pos.operate',
            'inventory.view',
            'report.view',
        ],
        'store_manager' => [
            'org.view',
            'store.view',
            'product.view',
            'order.view',
            'order.refund',
            'pos.operate',
            'inventory.view',
            'coupon.view',
        ],
        'cashier' => [
            'order.view',
            'pos.operate',
        ],
        'finance' => [
            'order.view',
            'order.refund',
            'report.view',
            'finance.view',
        ],
        'warehouse' => [
            'product.view',
            'inventory.view',
            'inventory.manage',
        ],
    ];

    public const GUARD = 'web';

    public function run(OrgTreeService $tree): void
    {
        $this->seedOrgUnits($tree);
        $this->seedRolesAndPermissions();
    }

    /**
     * @return array<string, OrgUnit>
     */
    public function seedOrgUnits(OrgTreeService $tree): array
    {
        $units = [];

        foreach (self::ORG_UNITS as $definition) {
            $unit = OrgUnit::query()->where('code', $definition['code'])->first();

            if ($unit === null) {
                $parent = $definition['parent_code'] === null
                    ? null
                    : ($units[$definition['parent_code']] ?? null);

                $unit = $tree->create([
                    'parent_id' => $parent?->id,
                    'type' => $definition['type'],
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'sort' => $definition['sort'],
                    'status' => OrgUnit::STATUS_ACTIVE,
                ]);
            }

            $units[$definition['code']] = $unit;
        }

        return $units;
    }

    public function seedRolesAndPermissions(): void
    {
        if (! class_exists(Role::class) || ! class_exists(Permission::class)) {
            $this->warn('spatie/laravel-permission 未安装，跳过角色/权限 seeding');

            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permissionName) {
            Permission::findOrCreate($permissionName, self::GUARD);
        }

        foreach (self::ROLES as $roleName => $permissionNames) {
            $role = Role::findOrCreate($roleName, self::GUARD);
            $role->syncPermissions($permissionNames);
        }

        $registrar->forgetCachedPermissions();
    }

    private function warn(string $message): void
    {
        if (isset($this->command) && $this->command !== null) {
            $this->command->warn($message);
        }
    }
}
