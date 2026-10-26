<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Member;

use App\Domain\Member\Enums\PointsRuleType;
use App\Domain\Member\Enums\Status;
use App\Domain\Member\Models\MemberLevel;
use App\Domain\Member\Models\MemberPointsRule;
use App\Domain\Member\Services\PointsService;
use Database\Seeders\MemberSeeder;

class MemberSeederTest extends MemberTestCase
{
    public function test_seeder_is_idempotent_and_creates_levels_and_points_rules(): void
    {
        $this->seed(MemberSeeder::class);
        $this->seed(MemberSeeder::class);

        $this->assertSame(4, MemberLevel::query()->count());
        $this->assertSame(2, MemberPointsRule::query()->count());

        $normal = MemberLevel::query()->where('name', '普通会员')->firstOrFail();
        $this->assertSame('0.00', $normal->threshold);
        $this->assertSame('1.00', $normal->discount_rate);
        $this->assertSame(Status::ACTIVE, $normal->status);

        $earnRule = MemberPointsRule::query()->where('rule_type', PointsRuleType::EARN)->firstOrFail();
        $this->assertSame('1.00', $earnRule->ratio);
        $this->assertSame('all', $earnRule->scope);

        $expireRule = MemberPointsRule::query()->where('rule_type', PointsRuleType::EXPIRE)->firstOrFail();
        $this->assertSame('365.00', $expireRule->ratio);

        // 过期天数从规则读取
        $this->assertSame(365, $this->app->make(PointsService::class)->expireDays());
    }
}
