<?php

namespace App\Domain\Store\Services;

use App\Domain\Store\Exceptions\RegionException;
use App\Domain\Store\Models\Region;
use App\Domain\Store\Models\RegionStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 区域树服务：创建、移动、子树/祖先查询、门店绑区。
 *
 * 关键约束：
 * - 移动必须防环（不能挂到自身或自己的后代之下），并禁止跨租户移动；
 * - path/level 与 parent_id 保持同步，移动子树时批量重写后代 path 与 level；
 * - 绑门店时同一事务内同步 region_store（唯一约束兜底）与 stores.region_id（反范式字段）。
 */
class RegionService
{
    /**
     * 创建区域节点（自动计算 path / level）。
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Region
    {
        $code = trim((string) ($attributes['code'] ?? ''));
        if ($code === '') {
            throw new InvalidArgumentException('区域 code 不能为空');
        }
        $attributes['code'] = $code;

        $parent = $this->resolveParent($attributes['parent_id'] ?? null);

        return DB::transaction(function () use ($attributes, $parent): Region {
            $region = new Region;
            $region->fill($attributes);
            $region->parent_id = $parent?->getKey();
            $region->level = $parent instanceof Region ? (int) $parent->level + 1 : 1;
            $region->path = '';
            $region->save();

            // id 生成后才能拼出物化路径，如根为 "1/"，其子为 "1/2/"。
            $region->path = ($parent?->pathPrefix() ?? '').(int) $region->getKey().'/';
            $region->save();

            return $region->refresh();
        });
    }

    /**
     * 移动区域到新的父级（null 表示移到根）。
     */
    public function move(Region $region, ?Region $newParent): Region
    {
        if ($newParent instanceof Region && (int) $newParent->getKey() === (int) $region->getKey()) {
            throw RegionException::selfAsParent();
        }

        if ($newParent instanceof Region && $this->isDescendantOf($newParent, $region)) {
            throw RegionException::cycle((int) $region->getKey(), (int) $newParent->getKey());
        }

        if ($newParent instanceof Region
            && $region->tenant_id !== null
            && $newParent->tenant_id !== null
            && (int) $region->tenant_id !== (int) $newParent->tenant_id) {
            throw RegionException::tenantMismatch();
        }

        $oldPath = (string) $region->path;
        $oldLevel = (int) $region->level;
        $newPath = ($newParent?->pathPrefix() ?? '').(int) $region->getKey().'/';
        $newLevel = $newParent instanceof Region ? (int) $newParent->level + 1 : 1;

        return DB::transaction(function () use ($region, $newParent, $oldPath, $oldLevel, $newPath, $newLevel): Region {
            $region->parent_id = $newParent?->getKey();
            $region->path = $newPath;
            $region->level = $newLevel;
            $region->save();

            if ($oldPath !== '' && $oldPath !== $newPath) {
                $levelDelta = $newLevel - $oldLevel;

                $descendants = Region::withTrashed()
                    ->where('path', 'like', $oldPath.'%')
                    ->whereKeyNot($region->getKey())
                    ->get();

                foreach ($descendants as $descendant) {
                    $descendant->path = $newPath.substr((string) $descendant->path, strlen($oldPath));
                    $descendant->level = max(1, (int) $descendant->level + $levelDelta);
                    $descendant->save();
                }
            }

            return $region->refresh();
        });
    }

    /**
     * 子树查询（可含自身）。
     *
     * @return Builder<Region>
     */
    public function descendants(Region $region, bool $includeSelf = false): Builder
    {
        if (! $includeSelf) {
            return $region->descendants();
        }

        return Region::query()
            ->whereIn('id', $this->descendantIds($region, true))
            ->orderBy('level')
            ->orderBy('id');
    }

    /**
     * 子树 id 列表（不含自身）。
     *
     * @return array<int, int>
     */
    public function descendantIds(Region $region, bool $includeSelf = false): array
    {
        $ids = Region::query()
            ->where('path', 'like', $region->pathPrefix().'%')
            ->whereKeyNot($region->getKey())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($includeSelf) {
            array_unshift($ids, (int) $region->getKey());
        }

        return $ids;
    }

    /**
     * 祖先链（由近到远，不含自身）。
     *
     * @return Collection<int, Region>
     */
    public function ancestors(Region $region): Collection
    {
        /** @var Collection<int, Region> $ancestors */
        $ancestors = $region->ancestors()->get();

        return $ancestors;
    }

    /**
     * 判断 $candidate 是否为 $ancestor 的后代。
     */
    public function isDescendantOf(Region $candidate, Region $ancestor): bool
    {
        if ((string) $candidate->path === '') {
            return false;
        }

        return str_starts_with((string) $candidate->path, $ancestor->pathPrefix())
            && (int) $candidate->getKey() !== (int) $ancestor->getKey();
    }

    /**
     * 门店绑区：置 stores.region_id，并写入/更新 region_store（unique(store_id)）。
     */
    public function assignStore(Region $region, int $storeId): RegionStore
    {
        if (! $region->isActive()) {
            throw RegionException::disabled((int) $region->getKey());
        }

        $storeExists = DB::table('stores')
            ->where('id', $storeId)
            ->whereNull('deleted_at')
            ->exists();

        if (! $storeExists) {
            throw RegionException::storeNotFound($storeId);
        }

        return DB::transaction(function () use ($region, $storeId): RegionStore {
            /** @var RegionStore $regionStore */
            $regionStore = RegionStore::query()->updateOrCreate(
                ['store_id' => $storeId],
                ['region_id' => (int) $region->getKey()]
            );

            DB::table('stores')
                ->where('id', $storeId)
                ->update([
                    'region_id' => (int) $region->getKey(),
                    'updated_at' => now(),
                ]);

            return $regionStore->refresh();
        });
    }

    /**
     * 门店所属区域（读 region_store 事实表；未绑定时返回 null）。
     */
    public function regionOfStore(int $storeId): ?Region
    {
        $regionStore = RegionStore::query()->where('store_id', $storeId)->first();

        if ($regionStore === null) {
            return null;
        }

        return Region::query()->find($regionStore->region_id);
    }

    private function resolveParent(mixed $parentId): ?Region
    {
        if ($parentId === null || $parentId === '' || (int) $parentId === 0) {
            return null;
        }

        return Region::query()->findOrFail((int) $parentId);
    }
}
