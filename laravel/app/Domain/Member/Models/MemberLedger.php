<?php

declare(strict_types=1);

namespace App\Domain\Member\Models;

use App\Domain\Member\Exceptions\LedgerImmutableException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 会员台账（只追加）。
 *
 * 表结构没有 updated_at；模型层禁止 update/delete，
 * 任何余额变动都必须通过 MemberAccountService 追加一条新记录。
 *
 * @property int $id
 * @property int $member_id
 * @property string $account_type
 * @property string $direction in/out
 * @property string $amount
 * @property string $biz_type recharge/consume/refund/adjust/expire
 * @property string|null $biz_id
 * @property string $idempotency_key
 * @property string $balance_after
 * @property int|null $operator_id
 * @property Carbon|null $created_at
 */
class MemberLedger extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'member_ledger';

    protected $fillable = [
        'member_id',
        'account_type',
        'direction',
        'amount',
        'biz_type',
        'biz_id',
        'idempotency_key',
        'balance_after',
        'operator_id',
    ];

    protected $casts = [
        'member_id' => 'integer',
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'operator_id' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $ledger): void {
            throw LedgerImmutableException::cannotUpdate($ledger->getKey());
        });

        static::deleting(function (self $ledger): void {
            throw LedgerImmutableException::cannotDelete($ledger->getKey());
        });
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }
}
