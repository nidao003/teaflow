<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Member;

use App\Domain\Member\Enums\AccountType;
use App\Domain\Member\Enums\LedgerBizType;
use App\Domain\Member\Enums\LedgerDirection;
use App\Domain\Member\Exceptions\InsufficientBalanceException;
use App\Domain\Member\Models\MemberAccount;
use App\Domain\Member\Models\MemberLedger;
use App\Domain\Member\Services\MemberAccountService;
use InvalidArgumentException;

class MemberAccountServiceTest extends MemberTestCase
{
    private MemberAccountService $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = $this->app->make(MemberAccountService::class);
    }

    public function test_recharge_consume_refund_keeps_balance_and_writes_three_ledger_entries(): void
    {
        $member = $this->createMember();

        $this->accounts->recharge($member->getKey(), '100.00', 'idem-recharge-1', 'pay-1');
        $this->accounts->consume($member->getKey(), '30.00', 'idem-consume-1', 'pay-2');
        $refundLedger = $this->accounts->refund($member->getKey(), '30.00', 'idem-refund-1', 'pay-2');

        $this->assertSame('100.00', $this->accounts->balance($member->getKey()));
        $this->assertSame(3, MemberLedger::query()->where('member_id', $member->getKey())->count());

        $entries = MemberLedger::query()
            ->where('member_id', $member->getKey())
            ->orderBy('id')
            ->get();

        $this->assertSame(['100.00', '70.00', '100.00'], $entries->pluck('balance_after')->all());
        $this->assertSame([LedgerDirection::IN, LedgerDirection::OUT, LedgerDirection::IN], $entries->pluck('direction')->all());
        $this->assertSame(
            [LedgerBizType::RECHARGE, LedgerBizType::CONSUME, LedgerBizType::REFUND],
            $entries->pluck('biz_type')->all()
        );
        $this->assertSame('100.00', $refundLedger->fresh()->balance_after);

        $account = MemberAccount::query()
            ->where('member_id', $member->getKey())
            ->where('account_type', AccountType::BALANCE)
            ->firstOrFail();
        $this->assertSame('100.00', $account->balance);
        $this->assertSame(3, $account->version);
    }

    public function test_balance_can_never_go_negative(): void
    {
        $member = $this->createMember();
        $this->accounts->recharge($member->getKey(), '50.00', 'idem-recharge-negative');

        try {
            $this->accounts->consume($member->getKey(), '80.00', 'idem-consume-negative');
            $this->fail('余额不足时应当抛出 InsufficientBalanceException');
        } catch (InsufficientBalanceException $e) {
            $this->assertStringContainsString('余额不足', $e->getMessage());
        }

        $this->assertSame('50.00', $this->accounts->balance($member->getKey()));
        $this->assertSame(1, MemberLedger::query()->count());
    }

    public function test_duplicate_recharge_idempotency_key_does_not_apply_twice(): void
    {
        $member = $this->createMember();

        $first = $this->accounts->recharge($member->getKey(), '100.00', 'idem-same-recharge');
        $second = $this->accounts->recharge($member->getKey(), '100.00', 'idem-same-recharge');

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame('100.00', $this->accounts->balance($member->getKey()));
        $this->assertSame(1, MemberLedger::query()->count());

        $account = MemberAccount::query()->where('member_id', $member->getKey())->firstOrFail();
        $this->assertSame(1, $account->version, '重复幂等请求不应增加账户版本号');
    }

    /**
     * 重复扣减（同一幂等键的两次 consume，模拟网关重试/并发重复请求）只扣一次。
     */
    public function test_repeated_consume_with_same_idempotency_key_only_deducts_once(): void
    {
        $member = $this->createMember();
        $this->accounts->recharge($member->getKey(), '100.00', 'idem-recharge-before-consume');

        $first = $this->accounts->consume($member->getKey(), '30.00', 'idem-duplicate-consume');
        $second = $this->accounts->consume($member->getKey(), '30.00', 'idem-duplicate-consume');

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame('70.00', $this->accounts->balance($member->getKey()));
        $this->assertSame(1, MemberLedger::query()->where('idempotency_key', 'idem-duplicate-consume')->count());
        $this->assertSame(2, MemberLedger::query()->count());
    }

    public function test_open_account_is_idempotent_per_account_type(): void
    {
        $member = $this->createMember();

        $first = $this->accounts->openAccount($member->getKey());
        $second = $this->accounts->openAccount($member->getKey());
        $points = $this->accounts->openAccount($member->getKey(), AccountType::POINTS);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(AccountType::BALANCE, $first->account_type);
        $this->assertSame(AccountType::POINTS, $points->account_type);
        $this->assertSame(2, MemberAccount::query()->count());
    }

    public function test_adjust_supports_in_and_out_directions(): void
    {
        $member = $this->createMember();

        $this->accounts->adjust($member->getKey(), '10.00', LedgerDirection::IN, 'idem-adjust-in');
        $out = $this->accounts->adjust($member->getKey(), '4.50', LedgerDirection::OUT, 'idem-adjust-out');

        $this->assertSame('5.50', $this->accounts->balance($member->getKey()));
        $this->assertSame('5.50', $out->fresh()->balance_after);
        $this->assertSame(2, MemberLedger::query()->where('biz_type', LedgerBizType::ADJUST)->count());
    }

    public function test_non_positive_amount_is_rejected(): void
    {
        $member = $this->createMember();

        foreach (['0', '-1.00', '0.00'] as $index => $amount) {
            try {
                $this->accounts->recharge($member->getKey(), $amount, 'idem-invalid-amount-'.$index);
                $this->fail('金额必须大于 0，'.$amount.' 应当被拒绝');
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('大于 0', $e->getMessage());
            }
        }

        $this->assertSame(0, MemberLedger::query()->count());
        $this->assertSame('0.00', $this->accounts->balance($member->getKey()));
    }
}
