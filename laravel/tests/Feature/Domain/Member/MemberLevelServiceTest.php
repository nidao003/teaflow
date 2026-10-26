<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Member;

use App\Domain\Member\Enums\Status;
use App\Domain\Member\Models\MemberLevel;
use App\Domain\Member\Services\MemberLevelService;

class MemberLevelServiceTest extends MemberTestCase
{
    private MemberLevelService $levels;

    protected function setUp(): void
    {
        parent::setUp();
        $this->levels = $this->app->make(MemberLevelService::class);

        $this->createLevel('普通会员', 0);
        $this->createLevel('银卡会员', 100);
        $this->createLevel('金卡会员', 500);
    }

    public function test_member_level_upgrades_when_growth_reaches_threshold(): void
    {
        $normal = MemberLevel::query()->where('name', '普通会员')->firstOrFail();
        $silver = MemberLevel::query()->where('name', '银卡会员')->firstOrFail();
        $gold = MemberLevel::query()->where('name', '金卡会员')->firstOrFail();

        $member = $this->createMember(['growth' => '0.00', 'level_id' => $normal->getKey()]);

        $member = $this->levels->addGrowth($member, '120.00');
        $this->assertSame($silver->getKey(), $member->level_id);
        $this->assertSame('120.00', $member->growth);

        $member = $this->levels->addGrowth($member, '400.00');
        $this->assertSame($gold->getKey(), $member->level_id);
        $this->assertSame('520.00', $member->growth);
        $this->assertSame($gold->getKey(), $member->fresh()->level_id);
    }

    public function test_member_level_downgrades_when_growth_decreases(): void
    {
        $gold = MemberLevel::query()->where('name', '金卡会员')->firstOrFail();
        $silver = MemberLevel::query()->where('name', '银卡会员')->firstOrFail();

        $member = $this->createMember(['growth' => '600.00', 'level_id' => $gold->getKey()]);

        $member->fill(['growth' => '120.00'])->save();
        $member = $this->levels->syncLevel($member->fresh());

        $this->assertSame($silver->getKey(), $member->level_id);
    }

    public function test_resolve_level_picks_highest_reached_threshold(): void
    {
        $this->assertSame('普通会员', $this->levels->resolveLevel('0')->name);
        $this->assertSame('普通会员', $this->levels->resolveLevel('99.99')->name);
        $this->assertSame('银卡会员', $this->levels->resolveLevel('100.00')->name);
        $this->assertSame('银卡会员', $this->levels->resolveLevel('499.99')->name);
        $this->assertSame('金卡会员', $this->levels->resolveLevel('500.00')->name);
        $this->assertSame('金卡会员', $this->levels->resolveLevel('9999.00')->name);
    }

    public function test_disabled_level_is_ignored(): void
    {
        MemberLevel::query()->where('name', '金卡会员')->update(['status' => Status::DISABLED]);

        $this->assertSame('银卡会员', $this->levels->resolveLevel('520.00')->name);
    }
}
