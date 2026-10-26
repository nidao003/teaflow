<?php

declare(strict_types=1);

namespace App\Domain\Member\Services;

use App\Domain\Member\Enums\AccountType;
use App\Domain\Member\Enums\LedgerBizType;
use App\Domain\Member\Enums\LedgerDirection;
use App\Domain\Member\Enums\PointsRuleType;
use App\Domain\Member\Enums\Status;
use App\Domain\Member\Models\Member;
use App\Domain\Member\Models\MemberAccount;
use App\Domain\Member\Models\MemberLedger;
use App\Domain\Member\Models\MemberPointsRule;
use App\Domain\Member\Support\Money;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 会员积分服务：获得、抵扣、过期。
 *
 * 积分存放在 member_accounts(account_type=points) 账户，台账复用 member_ledger；
 * members.points_balance 为冗余汇总字段，随记账同步。
 */
class PointsService
{
    public const DEFAULT_EXPIRE_DAYS = 365;

    public function __construct(
        protected MemberAccountService $accounts
    ) {}

    public function balance(Member $member): int
    {
        $cents = Money::toCents($this->accounts->balance($member->getKey(), AccountType::POINTS));

        return intdiv(max(0, $cents), 100);
    }

    public function earn(
        Member $member,
        int $points,
        string $idempotencyKey,
        ?string $bizId = null,
        ?int $operatorId = null
    ): MemberLedger {
        return $this->apply($member, LedgerDirection::IN, $points, $idempotencyKey, $bizId, $operatorId);
    }

    public function redeem(
        Member $member,
        int $points,
        string $idempotencyKey,
        ?string $bizId = null,
        ?int $operatorId = null
    ): MemberLedger {
        return $this->apply($member, LedgerDirection::OUT, $points, $idempotencyKey, $bizId, $operatorId);
    }

    /**
     * 积分过期，依据「先进先出」消耗顺序计算每笔入账的剩余积分。
     *
     * @param  Member|null  $member  只处理指定会员；为 null 时处理全部会员
     * @param  DateTimeInterface|null  $now  便于测试注入的时间基准
     * @param  int|null  $expireDays  有效期（天），默认读积分规则 expire.ratio，兜底 365 天
     * @return list<MemberLedger> 本次实际产生（或幂等命中）的过期台账
     */
    public function expire(
        ?Member $member = null,
        ?DateTimeInterface $now = null,
        ?int $expireDays = null
    ): array {
        $at = $now === null ? Carbon::now() : Carbon::instance($now);
        $days = $expireDays ?? $this->expireDays();

        $members = $member !== null
            ? [$member]
            : Member::query()->orderBy('id')->get()->all();

        $ledgers = [];
        foreach ($members as $item) {
            $ledger = $this->expireForMember($item, $at, $days);
            if ($ledger !== null) {
                $ledgers[] = $ledger;
            }
        }

        return $ledgers;
    }

    /**
     * 读取积分有效期（天）。
     */
    public function expireDays(): int
    {
        $ratio = MemberPointsRule::query()
            ->where('rule_type', PointsRuleType::EXPIRE)
            ->where('scope', 'all')
            ->where('status', Status::ACTIVE)
            ->value('ratio');

        $days = (int) ($ratio ?? 0);

        return $days > 0 ? $days : self::DEFAULT_EXPIRE_DAYS;
    }

    protected function apply(
        Member $member,
        string $direction,
        int $points,
        string $idempotencyKey,
        ?string $bizId,
        ?int $operatorId
    ): MemberLedger {
        if ($points <= 0) {
            throw new InvalidArgumentException('积分数量必须为正整数');
        }

        $ledger = $this->accounts->apply(
            $member->getKey(),
            AccountType::POINTS,
            $direction,
            $points,
            $direction === LedgerDirection::IN ? LedgerBizType::RECHARGE : LedgerBizType::CONSUME,
            $idempotencyKey,
            $bizId,
            $operatorId
        );

        $this->syncMemberPointsBalance($member);

        return $ledger;
    }

    protected function expireForMember(Member $member, CarbonInterface $now, int $expireDays): ?MemberLedger
    {
        $account = MemberAccount::query()
            ->where('member_id', $member->getKey())
            ->where('account_type', AccountType::POINTS)
            ->first();

        if ($account === null) {
            return null;
        }

        $cutoff = $now->copy()->subDays($expireDays);
        $expiredCents = min(
            $this->expiredPointsCents($member, $cutoff),
            max(0, Money::toCents((string) $account->balance))
        );

        if ($expiredCents <= 0) {
            return null;
        }

        $ledger = $this->accounts->apply(
            $member->getKey(),
            AccountType::POINTS,
            LedgerDirection::OUT,
            Money::fromCents($expiredCents),
            LedgerBizType::EXPIRE,
            sprintf('points-expire:%d:%s', $member->getKey(), $now->format('Y-m-d'))
        );

        $this->syncMemberPointsBalance($member);

        return $ledger;
    }

    /**
     * 按 FIFO 计算截止时间前入账、且尚未被消耗的积分（单位：分）。
     */
    protected function expiredPointsCents(Member $member, CarbonInterface $cutoff): int
    {
        $entries = MemberLedger::query()
            ->where('member_id', $member->getKey())
            ->where('account_type', AccountType::POINTS)
            ->orderBy('id')
            ->get();

        /** @var list<array{remaining: int, earned_at: CarbonInterface|null}> $layers */
        $layers = [];

        foreach ($entries as $entry) {
            $cents = Money::toCents((string) $entry->amount);

            if ($entry->direction === LedgerDirection::IN) {
                $layers[] = ['remaining' => $cents, 'earned_at' => $entry->getAttribute('created_at')];

                continue;
            }

            $consume = $cents;
            foreach ($layers as $index => $layer) {
                if ($consume <= 0) {
                    break;
                }
                $used = min($layer['remaining'], $consume);
                $layers[$index]['remaining'] -= $used;
                $consume -= $used;
            }
        }

        $expiredCents = 0;
        foreach ($layers as $layer) {
            if ($layer['remaining'] <= 0 || $layer['earned_at'] === null) {
                continue;
            }
            if ($layer['earned_at']->lte($cutoff)) {
                $expiredCents += $layer['remaining'];
            }
        }

        return $expiredCents;
    }

    /**
     * 同步 members.points_balance（冗余汇总字段）。
     */
    protected function syncMemberPointsBalance(Member $member): void
    {
        $cents = Money::toCents($this->accounts->balance($member->getKey(), AccountType::POINTS));
        $points = intdiv(max(0, $cents), 100);

        DB::table('members')->where('id', $member->getKey())->update([
            'points_balance' => $points,
            'updated_at' => Carbon::now(),
        ]);

        $member->setAttribute('points_balance', $points);
    }
}
