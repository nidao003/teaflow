<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Marketing\Enums\CampaignScope;
use App\Domain\Marketing\Enums\CampaignStatus;
use App\Domain\Marketing\Enums\CampaignType;
use App\Domain\Marketing\Enums\Status;
use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\CampaignSku;
use App\Domain\Marketing\Models\CampaignUsage;
use App\Domain\Marketing\Models\MemberTag;
use App\Domain\Marketing\Services\MemberPriceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 会员营销域演示数据：标签、会员价、活动、活动商品范围、使用记录。
 *
 * 幂等：按业务唯一键 updateOrCreate，重复执行不产生重复数据。
 */
class MarketingSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach ([
                ['name' => '新客', 'color' => '#67C23A'],
                ['name' => '高价值', 'color' => '#E6A23C'],
                ['name' => '沉睡客户', 'color' => '#909399'],
            ] as $tag) {
                MemberTag::query()->updateOrCreate(
                    ['name' => $tag['name']],
                    $tag + ['status' => Status::ACTIVE]
                );
            }

            $prices = $this->container->make(MemberPriceService::class);
            $prices->setPrice(1, 1, '18.00', '2026-01-01');
            $prices->setPrice(1, 2, '15.00', '2026-01-01');
            $prices->setPrice(2, 1, '12.00', '2026-01-01');

            $startAt = Carbon::now()->subDay();
            $endAt = Carbon::now()->addDays(30);

            $campaigns = [
                [
                    'name' => '周末满减',
                    'type' => CampaignType::FULL_REDUCTION,
                    'scope' => CampaignScope::ALL,
                    'rules_json' => [
                        'thresholds' => [
                            ['min_amount' => '100.00', 'discount_amount' => '10.00'],
                            ['min_amount' => '200.00', 'discount_amount' => '30.00'],
                        ],
                        'max_per_member' => 3,
                        'stackable' => false,
                    ],
                    'priority' => 10,
                ],
                [
                    'name' => '会员 9 折',
                    'type' => CampaignType::PERCENT,
                    'scope' => CampaignScope::ALL,
                    'rules_json' => [
                        'percent' => 90,
                        'max_discount' => '50.00',
                        'stackable' => true,
                        'stack_group' => 'discount',
                    ],
                    'priority' => 20,
                ],
                [
                    'name' => '第二件半价',
                    'type' => CampaignType::SECOND_HALF,
                    'scope' => CampaignScope::ALL,
                    'rules_json' => ['stackable' => false],
                    'priority' => 5,
                ],
                [
                    'name' => '满 88 赠柠檬茶',
                    'type' => CampaignType::GIFT,
                    'scope' => CampaignScope::ALL,
                    'rules_json' => [
                        'min_amount' => '88.00',
                        'gift_sku_id' => 9,
                        'gift_quantity' => 1,
                        'stackable' => true,
                        'stack_group' => 'gift',
                    ],
                    'priority' => 30,
                ],
            ];

            /** @var array<string, Campaign> $campaignModels */
            $campaignModels = [];
            foreach ($campaigns as $row) {
                $campaignModels[$row['name']] = Campaign::query()->updateOrCreate(
                    ['name' => $row['name']],
                    $row + [
                        'store_id' => null,
                        'start_at' => $startAt,
                        'end_at' => $endAt,
                        'status' => CampaignStatus::ACTIVE,
                    ]
                );
            }

            // 第二件半价只对 SKU 1/2 生效
            foreach ([1, 2] as $skuId) {
                CampaignSku::query()->updateOrCreate(
                    [
                        'campaign_id' => (int) $campaignModels['第二件半价']->getKey(),
                        'product_sku_id' => $skuId,
                    ],
                    []
                );
            }

            CampaignUsage::query()->updateOrCreate(
                [
                    'campaign_id' => (int) $campaignModels['周末满减']->getKey(),
                    'member_id' => 1,
                    'order_number' => 'TF202610260001',
                ],
                [
                    'amount' => '120.00',
                    'used_at' => Carbon::now(),
                ]
            );
        });
    }
}
