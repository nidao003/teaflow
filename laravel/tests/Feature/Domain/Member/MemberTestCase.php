<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Member;

use App\Domain\Member\Enums\Status;
use App\Domain\Member\Models\Member;
use App\Domain\Member\Models\MemberLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 会员域测试基类：每个测试用 RefreshDatabase 重建 sqlite 内存库。
 */
abstract class MemberTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createLevel(
        string $name,
        int|float|string $threshold,
        int|float|string $discountRate = '1.00'
    ): MemberLevel {
        return MemberLevel::query()->create([
            'name' => $name,
            'threshold' => $threshold,
            'discount_rate' => $discountRate,
            'benefits_json' => ['name' => $name],
            'status' => Status::ACTIVE,
        ]);
    }

    protected function createMember(array $attributes = []): Member
    {
        $member = new Member;
        $member->fill(array_merge([
            'user_id' => null,
            'phone_hash' => hash('sha256', '13800000000'),
            'points_balance' => 0,
            'growth' => '0.00',
            'status' => Status::ACTIVE,
            'joined_at' => now(),
        ], $attributes));
        $member->save();

        return $member;
    }
}
