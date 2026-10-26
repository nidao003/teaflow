<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Marketing;

use App\Domain\Marketing\Enums\CampaignScope;
use App\Domain\Marketing\Enums\CampaignStatus;
use App\Domain\Marketing\Enums\CampaignType;
use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Services\CampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 会员营销域测试基类：RefreshDatabase 重建 sqlite 内存库。
 *
 * 只插入营销域所需的最小外部数据（product_skus / member_levels / members），
 * 不修改、不依赖其他域的服务实现。
 */
abstract class MarketingTestCase extends TestCase
{
    use RefreshDatabase;

    private int $skuSequence = 0;

    private int $levelSequence = 0;

    private int $memberSequence = 0;

    private int $campaignSequence = 0;

    protected function createSku(int|float|string $price = '30.00'): int
    {
        $this->skuSequence++;

        return (int) DB::table('product_skus')->insertGetId([
            'product_id' => 1,
            'spec_key' => '默认规格-'.$this->skuSequence,
            'price' => $price,
            'market_price' => $price,
            'cost' => '0.00',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createLevel(string $name = '银卡会员'): int
    {
        $this->levelSequence++;

        return (int) DB::table('member_levels')->insertGetId([
            'name' => $name.$this->levelSequence,
            'threshold' => 0,
            'discount_rate' => '1.00',
            'benefits_json' => json_encode([], JSON_THROW_ON_ERROR),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function createMember(?int $memberLevelId = null): int
    {
        $this->memberSequence++;

        return (int) DB::table('members')->insertGetId([
            'user_id' => null,
            'phone_hash' => hash('sha256', '1380000'.$this->memberSequence),
            'level_id' => $memberLevelId,
            'points_balance' => 0,
            'growth' => '0.00',
            'status' => 1,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createCampaign(array $overrides = []): Campaign
    {
        $this->campaignSequence++;

        return $this->app->make(CampaignService::class)->createCampaign(array_merge([
            'name' => '测试活动-'.$this->campaignSequence,
            'type' => CampaignType::FULL_REDUCTION,
            'scope' => CampaignScope::ALL,
            'rules_json' => [
                'thresholds' => [
                    ['min_amount' => '100.00', 'discount_amount' => '10.00'],
                ],
            ],
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
            'status' => CampaignStatus::ACTIVE,
            'priority' => 0,
        ], $overrides));
    }

    /**
     * @return array{sku_id:int, price:int|float|string, quantity:int}
     */
    protected function item(int $skuId, int|float|string $price, int $quantity = 1): array
    {
        return ['sku_id' => $skuId, 'price' => $price, 'quantity' => $quantity];
    }
}
