<?php

namespace App\Domain\Org\Services;

use App\Domain\Org\Exceptions\OrgTreeException;
use App\Domain\Org\Models\OrgUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 组织树服务：创建、移动、查子树/祖先链，并保证 path/level 与 parent_id 一致。
 *
 * 关键约束：
 * - 任何移动都必须防环（不能挂到自身或自己的后代之下）；
 * - path 为物化路径，移动子树时批量重写后代 path 与 level；
 * - 父子关系为逻辑外键，本服务不依赖数据库外键约束。
 */
class OrgTreeService
{
    /**
     * 创建组织节点（自动计算 path / level）。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): OrgUnit
    {
        $type = $attributes['type'] ?? null;
        if (! in_array($type, OrgUnit::TYPES, true)) {
            throw OrgTreeException::invalidType((string) $type);
        }

        $parent = $this->resolveParent($attributes['parent_id'] ?? null);

        return DB::transaction(function () use ($attributes, $parent): OrgUnit {
            $unit = new OrgUnit;
            $unit->fill($attributes);
            $unit->parent_id = $parent?->id;
            $unit->level = $parent ? $parent->level + 1 : 1;
            $unit->path = '';
            $unit->save();

            // id 生成后才能拼出物化路径，如根为 "1/"，其子为 "1/2/"。
            $unit->path = ($parent?->pathPrefix() ?? '').$unit->id.'/';
            $unit->save();

            return $unit->refresh();
        });
    }

    /**
     * 移动节点到新的父级（null 表示移到根）。
     */
    public function move(OrgUnit $unit, ?OrgUnit $newParent): OrgUnit
    {
        if ($newParent !== null && $newParent->id === $unit->id) {
            throw OrgTreeException::selfAsParent();
        }

        if ($newParent !== null && $this->isDescendantOf($newParent, $unit)) {
            throw OrgTreeException::cycle((int) $unit->id, (int) $newParent->id);
        }

        if ($newParent !== null
            && $unit->tenant_id !== null
            && $newParent->tenant_id !== null
            && (int) $unit->tenant_id !== (int) $newParent->tenant_id) {
            throw OrgTreeException::tenantMismatch();
        }

        $oldPath = (string) $unit->path;
        $oldLevel = (int) $unit->level;
        $newPath = ($newParent?->pathPrefix() ?? '').$unit->id.'/';
        $newLevel = $newParent ? (int) $newParent->level + 1 : 1;

        return DB::transaction(function () use ($unit, $newParent, $oldPath, $oldLevel, $newPath, $newLevel): OrgUnit {
            $unit->parent_id = $newParent?->id;
            $unit->path = $newPath;
            $unit->level = $newLevel;
            $unit->save();

            if ($oldPath !== '' && $oldPath !== $newPath) {
                $levelDelta = $newLevel - $oldLevel;

                $descendants = OrgUnit::withTrashed()
                    ->where('path', 'like', $oldPath.'%')
                    ->whereKeyNot($unit->id)
                    ->get();

                foreach ($descendants as $descendant) {
                    $descendant->path = $newPath.substr((string) $descendant->path, strlen($oldPath));
                    $descendant->level = max(1, (int) $descendant->level + $levelDelta);
                    $descendant->save();
                }
            }

            return $unit->refresh();
        });
    }

    /**
     * 子树查询（可含自身）。
     */
    public function descendants(OrgUnit $unit, bool $includeSelf = false): Builder
    {
        if (! $includeSelf) {
            return $unit->descendants();
        }

        return OrgUnit::query()
            ->whereIn('id', $this->descendantIds($unit, true))
            ->orderBy('level')
            ->orderBy('id');
    }

    /**
     * 子树 id 列表（不含自身）。
     *
     * @return array<int, int>
     */
    public function descendantIds(OrgUnit $unit, bool $includeSelf = false): array
    {
        $ids = OrgUnit::query()
            ->where('path', 'like', $unit->pathPrefix().'%')
            ->whereKeyNot($unit->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($includeSelf) {
            array_unshift($ids, (int) $unit->id);
        }

        return $ids;
    }

    /**
     * 祖先链（由近到远，不含自身）。
     *
     * @return Collection<int, OrgUnit>
     */
    public function ancestors(OrgUnit $unit): Collection
    {
        /** @var Collection<int, OrgUnit> $ancestors */
        $ancestors = $unit->ancestors()->get();

        return $ancestors;
    }

    /**
     * 判断 $candidate 是否为 $ancestor 的后代（含自身以外）。
     */
    public function isDescendantOf(OrgUnit $candidate, OrgUnit $ancestor): bool
    {
        if ($candidate->path === null || $candidate->path === '') {
            return false;
        }

        return str_starts_with((string) $candidate->path, $ancestor->pathPrefix())
            && (int) $candidate->id !== (int) $ancestor->id;
    }

    private function resolveParent(mixed $parentId): ?OrgUnit
    {
        if ($parentId === null || $parentId === '' || (int) $parentId === 0) {
            return null;
        }

        return OrgUnit::query()->findOrFail((int) $parentId);
    }
}
