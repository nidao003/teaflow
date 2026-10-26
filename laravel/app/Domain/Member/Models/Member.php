<?php

declare(strict_types=1);

namespace App\Domain\Member\Models;

use App\Domain\Member\Enums\Status;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 会员主体。user_id、phone_hash 为可空的关联/脱敏标识，
 * phone 明文不入库，仅保存哈希。
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $phone_hash
 * @property int|null $level_id
 * @property int $points_balance 积分余额（冗余汇总，源头是 points 账户余额）
 * @property string $growth 成长值
 * @property int $status
 * @property Carbon|null $joined_at
 */
class Member extends Model
{
    protected $table = 'members';

    protected $fillable = [
        'user_id',
        'phone_hash',
        'level_id',
        'points_balance',
        'growth',
        'status',
        'joined_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'level_id' => 'integer',
        'points_balance' => 'integer',
        'growth' => 'decimal:2',
        'status' => 'integer',
        'joined_at' => 'datetime',
    ];

    public function level(): BelongsTo
    {
        return $this->belongsTo(MemberLevel::class, 'level_id', 'id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(MemberAccount::class, 'member_id', 'id');
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(MemberLedger::class, 'member_id', 'id');
    }

    public function isActive(): bool
    {
        return (int) $this->status === Status::ACTIVE;
    }
}
