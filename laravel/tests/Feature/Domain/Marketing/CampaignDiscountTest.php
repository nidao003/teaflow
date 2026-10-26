<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Marketing;

use App\Domain\Marketing\Enums\CampaignType;
use App\Domain\Marketing\Exceptions\CampaignException;
use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Services\CampaignService;
use InvalidArgumentException;

class CampaignDiscountTest extends MarketingTestCase
{
    private CampaignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(CampaignService::class);
    }

    public function test_full_reduction_picks_highest_threshold(): void
    {
        $sku = $this->createSku('100.00');
        $campaign = $this->createCampaign([
            'rules_json' => [
                'thresholds' => [
                    ['min_amount' => '100.00', 'discount_amount' => '10.00'],
                    ['min_amount' => '200.00', 'discount_amount' => '30.00'],
                ],
            ],
        ]);

        $this->assertSame('10.00', $this->service->calcDiscount($campaign, [$this->item($sku, '100.00')]));
        $this->assertSame('30.00', $this->service->calcDiscount($campaign, [$this->item($sku, '250.00')]));
        $this->assertSame('0.00', $this->service->calcDiscount($campaign, [$this->item($sku, '50.00')]));
    }

    public function test_percent_discount_with_cap(): void
    {
        $sku = $this->createSku('100.00');
        $campaign = $this->createCampaign([
            'type' => CampaignType::PERCENT,
            'rules_json' => ['percent' => 90, 'max_discount' => '20.00'],
        ]);

        $this->assertSame('10.00', $this->service->calcDiscount($campaign, [$this->item($sku, '100.00')]));
        $this->assertSame('20.00', $this->service->calcDiscount($campaign, [$this->item($sku, '300.00')]));
    }

    public function test_second_half_price(): void
    {
        $firstSku = $this->createSku('20.00');
        $secondSku = $this->createSku('30.00');
        $campaign = $this->createCampaign([
            'type' => CampaignType::SECOND_HALF,
            'rules_json' => [],
        ]);

        $this->assertSame('10.00', $this->service->calcDiscount($campaign, [$this->item($firstSku, '20.00', 2)]));
        $this->assertSame('10.00', $this->service->calcDiscount($campaign, [$this->item($firstSku, '20.00', 3)]));
        $this->assertSame('25.00', $this->service->calcDiscount($campaign, [
            $this->item($firstSku, '20.00', 2),
            $this->item($secondSku, '30.00', 2),
        ]));
    }

    public function test_second_half_only_counts_campaign_skus(): void
    {
        $firstSku = $this->createSku('20.00');
        $secondSku = $this->createSku('30.00');
        $campaign = $this->createCampaign([
            'type' => CampaignType::SECOND_HALF,
            'rules_json' => [],
            'skus' => [$firstSku],
        ]);

        $this->assertSame('10.00', $this->service->calcDiscount($campaign, [
            $this->item($firstSku, '20.00', 2),
            $this->item($secondSku, '30.00', 2),
        ]));
    }

    public function test_gift_campaign(): void
    {
        $sku = $this->createSku('100.00');
        $campaign = $this->createCampaign([
            'type' => CampaignType::GIFT,
            'rules_json' => [
                'min_amount' => '88.00',
                'gift_sku_id' => 9,
                'gift_quantity' => 2,
            ],
        ]);

        $this->assertSame(
            ['product_sku_id' => 9, 'quantity' => 2],
            $this->service->gift($campaign, '100.00')
        );
        $this->assertNull($this->service->gift($campaign, '50.00'));
        $this->assertSame('0.00', $this->service->calcDiscount($campaign, [$this->item($sku, '100.00')]));
    }

    public function test_gift_can_stack_with_discount_campaign(): void
    {
        $sku = $this->createSku('100.00');
        $items = [$this->item($sku, '100.00')];

        $discount = $this->createCampaign([
            'name' => '会员 9 折',
            'type' => CampaignType::PERCENT,
            'priority' => 20,
            'rules_json' => ['percent' => 90, 'stackable' => true, 'stack_group' => 'main'],
        ]);
        $gift = $this->createCampaign([
            'name' => '满 88 赠品',
            'type' => CampaignType::GIFT,
            'priority' => 30,
            'rules_json' => [
                'min_amount' => '88.00',
                'gift_sku_id' => 9,
                'gift_quantity' => 1,
                'stackable' => true,
                'stack_group' => 'gift',
            ],
        ]);

        $result = $this->service->resolve([$discount, $gift], $items, null, 1);

        $this->assertSame('10.00', $result['discount']);
        $this->assertCount(1, $result['gifts']);
        $this->assertSame(9, $result['gifts'][0]['product_sku_id']);
        $this->assertSame(1, $result['gifts'][0]['quantity']);
        $this->assertTrue($result['stacked']);
    }

    public function test_invalid_rules_are_rejected(): void
    {
        $invalidTypes = [
            CampaignType::PERCENT,
            CampaignType::FULL_REDUCTION,
            CampaignType::GIFT,
        ];

        foreach ($invalidTypes as $index => $type) {
            try {
                $this->createCampaign([
                    'name' => '非法活动-'.$index,
                    'type' => $type,
                    'rules_json' => [],
                ]);
                $this->fail('非法活动规则应当被拒绝：'.$type);
            } catch (CampaignException) {
                // expected
            }
        }

        $this->assertSame(0, Campaign::query()->count());
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->createCampaign(['type' => 'buy_one_get_one']);
    }

    public function test_items_subtotal_uses_cents(): void
    {
        $firstSku = $this->createSku('19.90');
        $secondSku = $this->createSku('0.10');

        $this->assertSame('39.90', $this->service->itemsSubtotal([
            $this->item($firstSku, '19.90', 2),
            $this->item($secondSku, '0.10'),
        ]));
    }
}
