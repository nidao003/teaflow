<?php

namespace App\Domain\Org\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 组织单元（公司 / 区域 / 门店）。
 *
 * 约定：
 * - type 与 status 均为字符串常量，不用数据库 enum；
 * - path 为物化路径（"1/2/5/"），descendants()/ancestors() 基于 path 计算，避免递归 SQL；
 * - 父子关系为逻辑外键，数据库层不建外键约束。
 */
class OrgUnit extends Model
{
    use SoftDeletes;

    public const TYPE_COMPANY = 'company';

    public const TYPE_REGION = 'region';

    public const TYPE_STORE = 'store';

    public const TYPES = [
        self::TYPE_COMPANY,
        self::TYPE_REGION,
        self::TYPE_STORE,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_DISABLED,
    ];

    protected $table = 'org_units';

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'type',
        'code',
        'name',
        'path',
        'level',
        'sort',
        'status',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'parent_id' => 'integer',
        'level' => 'integer',
        'sort' => 'integer',
    ];

    protected $attributes = [
        'level' => 1,
        'sort' => 0,
        'status' => self::STATUS_ACTIVE,
    ];

    /**
     * 父级组织。
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    /**
     * 直接下级组织（不含孙子级）。
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id', 'id')
            ->orderBy('sort')
            ->orderBy('id');
    }

    /**
     * 归属该组织的用户。
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'org_unit_id', 'id');
    }

    /**
     * 所有后代组织（不含自身）。
     *
     * 注意：返回的是查询构造器而非 Eloquent 关联，调用时需 ->get() / ->count()。
     */
    public function descendants(): Builder
    {
        // path 为空说明数据未初始化，返回空集而不是 "like '%'"（避免误匹配全表）。
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
     * 所有祖先组织（由近到远，不含自身）。
     */
    public function ancestors(): Builder
    {
        $ids = $this->ancestorIds();

        return static::query()
            ->whereIn('id', $ids)
            ->orderByDesc('level')
            ->orderByDesc('id');
    }

    /**
     * 祖先节点 id 列表（由近到远，不含自身）。
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
     * path 的分段（不含空值）。
     *
     * @return array<int, string>
     */
    public function pathSegments(): array
    {
        return array_values(array_filter(explode('/', (string) $this->path), fn (string $s): bool => $s !== ''));
    }

    /**
     * 子树前缀：根节点为 "1/"，子节点以此前缀匹配后代。
     */
    public function pathPrefix(): string
    {
        return trim((string) $this->path, '/') === ''
            ? ''
            : trim((string) $this->path, '/').'/';
    }

    public function isCompany(): bool
    {
        return $this->type === self::TYPE_COMPANY;
    }

    public function isRegion(): bool
    {
        return $this->type === self::TYPE_REGION;
    }

    public function isStore(): bool
    {
        return $this->type === self::TYPE_STORE;
    }

    /**
     * 按组织类型过滤。
     */
    public function scopeOfType(Builder $query, string ...$types): Builder
    {
        return $query->whereIn('type', $types);
    }

    /**
     * 仅启用状态。
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
