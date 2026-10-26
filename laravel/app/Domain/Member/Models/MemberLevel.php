<?php

declare(strict_types=1);

namespace App\Domain\Member\Models;

use App\Domain\Member\Enums\Status;
use Illuminate\Database\Eloquent\Model;

/**
 * 会员等级。
 *
 * @property int $id
 * @property string $name
 * @property string $threshold 升级所需成长值
 * @property string $discount_rate 折扣率，0.95 表示 95 折
 * @property array|null $benefits_json
 * @property int $status
 */
class MemberLevel extends Model
{
    protected $table = 'member_levels';

    protected $fillable = [
        'name',
        'threshold',
        'discount_rate',
        'benefits_json',
        'status',
    ];

    protected $casts = [
        'threshold' => 'decimal:2',
        'discount_rate' => 'decimal:2',
        'benefits_json' => 'array',
        'status' => 'integer',
    ];

    public function members()
    {
        return $this->hasMany(Member::class, 'level_id', 'id');
    }

    public function isActive(): bool
    {
        return (int) $this->status === Status::ACTIVE;
    }
}
