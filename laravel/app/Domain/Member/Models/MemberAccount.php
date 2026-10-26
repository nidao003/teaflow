<?php

declare(strict_types=1);

namespace App\Domain\Member\Models;

use App\Domain\Member\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 会员资金/积分账户，同一会员同一账户类型唯一。
 *
 * version 作为乐观锁版本号，每次余额变动 +1；
 * 实际扣减由 MemberAccountService 在数据库事务内用 lockForUpdate 串行化。
 *
 * @property int $id
 * @property int $member_id
 * @property string $account_type
 * @property string $balance
 * @property string $frozen
 * @property int $version
 */
class MemberAccount extends Model
{
    protected $table = 'member_accounts';

    protected $fillable = [
        'member_id',
        'account_type',
        'balance',
        'frozen',
        'version',
    ];

    protected $casts = [
        'member_id' => 'integer',
        'balance' => 'decimal:2',
        'frozen' => 'decimal:2',
        'version' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id', 'id');
    }

    /**
     * 可用余额 = 余额 - 冻结金额（当前域未实现冻结写入，先按 0 处理）。
     */
    public function availableBalance(): string
    {
        $balance = (int) round(((float) $this->balance) * 100);
        $frozen = (int) round(((float) $this->frozen) * 100);

        return Money::fromCents(max(0, $balance - $frozen));
    }
}
