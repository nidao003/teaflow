<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Member\Enums\PointsRuleType;
use App\Domain\Member\Enums\Status;
use App\Domain\Member\Models\MemberLevel;
use App\Domain\Member\Models\MemberPointsRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 会员域基础数据：等级阶梯 + 积分规则。
 *
 * 幂等：重复执行不会产生重复数据（按 name / rule_type+scope 更新）。
 */
class MemberSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $levels = [
                [
                    'name' => '普通会员',
                    'threshold' => 0,
                    'discount_rate' => '1.00',
                    'benefits_json' => ['积分累计'],
                    'status' => Status::ACTIVE,
                ],
                [
                    'name' => '银卡会员',
                    'threshold' => 100,
                    'discount_rate' => '0.98',
                    'benefits_json' => ['积分累计', '98 折'],
                    'status' => Status::ACTIVE,
                ],
                [
                    'name' => '金卡会员',
                    'threshold' => 500,
                    'discount_rate' => '0.95',
                    'benefits_json' => ['积分累计', '95 折', '生日礼'],
                    'status' => Status::ACTIVE,
                ],
                [
                    'name' => '黑金会员',
                    'threshold' => 2000,
                    'discount_rate' => '0.90',
                    'benefits_json' => ['积分累计', '9 折', '生日礼', '专属客服'],
                    'status' => Status::ACTIVE,
                ],
            ];

            foreach ($levels as $level) {
                MemberLevel::query()->updateOrCreate(
                    ['name' => $level['name']],
                    $level
                );
            }

            $rules = [
                // earn：每消费 1 元累计 1 积分
                ['rule_type' => PointsRuleType::EARN, 'scope' => 'all', 'ratio' => '1.00'],
                // expire：积分有效期 365 天（ratio 语义见 MemberPointsRule 注释）
                ['rule_type' => PointsRuleType::EXPIRE, 'scope' => 'all', 'ratio' => '365.00'],
            ];

            foreach ($rules as $rule) {
                MemberPointsRule::query()->updateOrCreate(
                    ['rule_type' => $rule['rule_type'], 'scope' => $rule['scope']],
                    $rule + ['status' => Status::ACTIVE]
                );
            }
        });
    }
}
