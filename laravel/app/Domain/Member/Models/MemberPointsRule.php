<?php

declare(strict_types=1);

namespace App\Domain\Member\Models;

use App\Domain\Member\Enums\Status;
use Illuminate\Database\Eloquent\Model;

/**
 * 积分规则。
 *
 * ratio 语义随 rule_type 变化：earn 为每元获得积分；expire 为积分有效期（天）。
 *
 * @property int $id
 * @property string $rule_type
 * @property string $ratio
 * @property string $scope
 * @property int $status
 */
class MemberPointsRule extends Model
{
    protected $table = 'member_points_rules';

    protected $fillable = [
        'rule_type',
        'ratio',
        'scope',
        'status',
    ];

    protected $casts = [
        'ratio' => 'decimal:2',
        'status' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', Status::ACTIVE);
    }
}
