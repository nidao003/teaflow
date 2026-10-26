<?php

namespace App\Domain\Store\Models;

use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 区域（树形，物化路径 "1/2/"）。
 *
 * - status 用字符串常量 active/disabled；
 * - parent_id 是逻辑外键（只建索引）；
 * - descendants()/ancestors() 基于 path 前缀，避免递归 SQL。
 */
class Region extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_DISABLED,
    ];

    protected $table = 'regions';

    protected $casts = [
        'tenant_id' => 'integer',
        'parent_id' => 'integer',
        'level' => 'integer',
    ];

    protected $attributes = [
        'level' => 1,
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * 父级区域。
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 直接下级区域。
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')->orderBy('id');
    }

    /**
     * 关联门店（经 region_store 多对多）。
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'region_store', 'region_id', 'store_id')
            ->withTimestamps();
    }

    /**
     * 关联表记录。
     */
    public function regionStores(): HasMany
    {
        return $this->hasMany(RegionStore::class, 'region_id', 'id');
    }

    /**
     * 所有后代区域（不含自身）。返回查询构造器，调用时需 ->get()/->count()。
     */
    public function descendants(): Builder
    {
        // path 为空说明数据未初始化，返回空集而不是 "like '%'"（避免误匹配全部区域）。
        if (trim((string) $this->path, '/') === '') {
            return static::query()->whereRaw('1 = 0');
        }

        return static::query()
            ->where('path', 'like', $this->pathPrefix().'%')
            ->whereKeyNot($this->getKey())
            ->orderBy('level')
            ->orderBy('id');
    }

    /**
     * 所有祖先区域（由近到远，不含自身）。
     */
    public function ancestors(): Builder
    {
        return static::query()
            ->whereIn('id', $this->ancestorIds())
            ->orderByDesc('level')
            ->orderByDesc('id');
    }

    /**
     * 祖先 id 列表（由近到远，不含自身）。
     *
     * @return array<int, int>
     */
    public function ancestorIds(): array
    {
        $ids = array_values(array_filter(
            array_map('intval', $this->pathSegments()),
            fn (int $id): bool => $id > 0 && $id !== (int) $this->getKey()
        ));

        return array_reverse($ids);
    }

    /**
     * path 分段。
     *
     * @return array<int, string>
     */
    public function pathSegments(): array
    {
        return array_values(array_filter(
            explode('/', (string) $this->path),
            fn (string $segment): bool => $segment !== ''
        ));
    }

    /**
     * 子树前缀："1/"。
     */
    public function pathPrefix(): string
    {
        $trimmed = trim((string) $this->path, '/');

        return $trimmed === '' ? '' : $trimmed.'/';
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
