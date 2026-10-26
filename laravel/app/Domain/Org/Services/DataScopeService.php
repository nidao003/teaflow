<?php

namespace App\Domain\Org\Services;

use App\Domain\Org\Models\OrgUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * 数据范围（Data Scope）服务。
 *
 * 把用户的数据范围翻译成查询条件，支持四种范围：
 * - all    ：全量（必须显式 data_scope='all' 才会放行，绝不因缺省而全表）；
 * - region ：用户所属组织及其所有下级组织；
 * - store  ：仅用户所属组织；
 * - self   ：仅本人（传 $selfColumn 时按 user_id 过滤；否则退化为仅本人组织）。
 *
 * 安全约定：user 为空、org_unit_id 为空、scope 非法 → 一律收窄为空集（0 行），
 * 不允许出现「无范围 = 全表」的隐式放行。
 */
class DataScopeService
{
    public const SCOPE_ALL = 'all';

    public const SCOPE_REGION = 'region';

    public const SCOPE_STORE = 'store';

    public const SCOPE_SELF = 'self';

    public const SCOPES = [
        self::SCOPE_ALL,
        self::SCOPE_REGION,
        self::SCOPE_STORE,
        self::SCOPE_SELF,
    ];

    public function __construct(private readonly OrgTreeService $tree) {}

    /**
     * 规整用户的 data_scope：非法值一律降级为 self（最小权限），不做全表放行。
     */
    public function scopeOf(?User $user): string
    {
        $scope = $user?->data_scope;

        return in_array($scope, self::SCOPES, true) ? $scope : self::SCOPE_SELF;
    }

    /**
     * 允许访问的组织 id 列表；null 表示不限制（仅 all）。
     *
     * @return array<int, int>|null
     */
    public function allowedOrgUnitIds(?User $user): ?array
    {
        if ($user === null) {
            return [];
        }

        return match ($this->scopeOf($user)) {
            self::SCOPE_ALL => null,
            self::SCOPE_REGION => $this->regionOrgUnitIds($user),
            default => $user->org_unit_id ? [(int) $user->org_unit_id] : [],
        };
    }

    /**
     * 给查询套上数据范围。
     *
     * @param  string  $column  组织列名，默认 org_unit_id；查 org_units 表时传 'id'
     * @param  string|null  $selfColumn  仅本人时使用的列名（如 user_id / created_by）
     */
    public function apply(
        Builder $query,
        ?User $user,
        string $column = 'org_unit_id',
        ?string $selfColumn = null
    ): Builder {
        $scope = $this->scopeOf($user);

        if ($scope === self::SCOPE_ALL) {
            return $query;
        }

        if ($scope === self::SCOPE_SELF && $selfColumn !== null) {
            return $user === null
                ? $this->denyAll($query)
                : $query->where($selfColumn, $user->id);
        }

        $ids = $this->allowedOrgUnitIds($user) ?? [];

        return $ids === [] ? $this->denyAll($query) : $query->whereIn($column, $ids);
    }

    /**
     * 查询组织单元时直接套数据范围。
     */
    public function applyToOrgUnits(?User $user): Builder
    {
        return $this->apply(OrgUnit::query(), $user, 'id');
    }

    /**
     * 用户可见的组织 id：自身 + 所有下级。
     *
     * @return array<int, int>
     */
    private function regionOrgUnitIds(User $user): array
    {
        if (! $user->org_unit_id) {
            return [];
        }

        $unit = OrgUnit::query()->find($user->org_unit_id);
        if ($unit === null) {
            return [];
        }

        return $this->tree->descendantIds($unit, true);
    }

    private function denyAll(Builder $query): Builder
    {
        return $query->whereRaw('1 = 0');
    }
}
