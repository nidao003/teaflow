<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Member;

use App\Domain\Member\Enums\LedgerBizType;
use App\Domain\Member\Enums\LedgerDirection;
use App\Domain\Member\Exceptions\InsufficientBalanceException;
use App\Domain\Member\Models\MemberLedger;
use App\Domain\Member\Services\PointsService;

class PointsServiceTest extends MemberTestCase
{
    private PointsService $points;

    protected function setUp(): void
    {
        parent::setUp();
        $this->points = $this->app->make(PointsService::class);
    }

    public function test_earn_and_redeem_points(): void
    {
        $member = $this->createMember();

        $earn = $this->points->earn($member, 100, 'points-earn-1', 'order-1');
        $this->assertSame(100, $this->points->balance($member));
        $this->assertSame(100, $member->fresh()->points_balance);
        $this->assertSame(LedgerDirection::IN, $earn->direction);
        $this->assertSame('100.00', $earn->fresh()->balance_after);

        $redeem = $this->points->redeem($member, 30, 'points-redeem-1', 'order-2');
        $this->assertSame(70, $this->points->balance($member));
        $this->assertSame(70, $member->fresh()->points_balance);
        $this->assertSame(LedgerDirection::OUT, $redeem->direction);
        $this->assertSame('70.00', $redeem->fresh()->balance_after);
        $this->assertSame(2, MemberLedger::query()->count());
    }

    public function test_redeem_more_than_balance_is_rejected(): void
    {
        $member = $this->createMember();
        $this->points->earn($member, 10, 'points-earn-insufficient');

        try {
            $this->points->redeem($member, 11, 'points-redeem-insufficient');
            $this->fail('积分不足时应当抛出 InsufficientBalanceException');
        } catch (InsufficientBalanceException $e) {
            $this->assertStringContainsString('余额不足', $e->getMessage());
        }

        $this->assertSame(10, $this->points->balance($member));
        $this->assertSame(1, MemberLedger::query()->count());
    }

    public function test_points_expire_after_configured_days_and_is_idempotent(): void
    {
        $member = $this->createMember();
        $this->points->earn($member, 100, 'points-earn-expire');

        // 366 天后已超过默认 365 天有效期
        $ledgers = $this->points->expire($member, now()->addDays(366));
        $this->assertCount(1, $ledgers);
        $this->assertSame(LedgerBizType::EXPIRE, $ledgers[0]->biz_type);
        $this->assertSame(LedgerDirection::OUT, $ledgers[0]->direction);
        $this->assertSame('100.00', $ledgers[0]->fresh()->amount);
        $this->assertSame(0, $this->points->balance($member));
        $this->assertSame(0, $member->fresh()->points_balance);

        // 同一时点重复执行不重复扣减
        $this->assertCount(0, $this->points->expire($member, now()->addDays(366)));
        $this->assertSame(2, MemberLedger::query()->count());
    }

    public function test_points_do_not_expire_before_expiry_window(): void
    {
        $member = $this->createMember();
        $this->points->earn($member, 100, 'points-earn-not-expired');

        $this->assertCount(0, $this->points->expire($member, now()->addDays(100)));
        $this->assertSame(100, $this->points->balance($member));
        $this->assertSame(1, MemberLedger::query()->count());
    }

    public function test_expire_only_takes_remaining_points_after_redeem(): void
    {
        $member = $this->createMember();
        $this->points->earn($member, 100, 'points-earn-partial');
        $this->points->redeem($member, 40, 'points-redeem-partial');

        $ledgers = $this->points->expire($member, now()->addDays(366));

        $this->assertCount(1, $ledgers);
        $this->assertSame('60.00', $ledgers[0]->fresh()->amount);
        $this->assertSame(0, $this->points->balance($member));
        $this->assertSame(0, $member->fresh()->points_balance);
    }
}
