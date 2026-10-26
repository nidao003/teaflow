<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Marketing;

use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\CampaignUsage;
use App\Domain\Marketing\Services\CampaignService;
use App\Domain\Marketing\Services\UsageService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

class UsageServiceTest extends MarketingTestCase
{
    private UsageService $service;

    private Campaign $campaign;

    private int $memberId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(UsageService::class);
        $this->campaign = $this->createCampaign();
        $this->memberId = $this->createMember();
    }

    public function test_record_usage_is_idempotent_by_order_number(): void
    {
        $first = $this->service->record(
            (int) $this->campaign->getKey(),
            $this->memberId,
            'ORDER-1',
            '120.00'
        );
        $second = $this->service->record(
            (int) $this->campaign->getKey(),
            $this->memberId,
            'ORDER-1',
            '120.00'
        );

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, CampaignUsage::query()->count());
        $this->assertSame(1, $this->service->countForMember((int) $this->campaign->getKey(), $this->memberId));
        $this->assertSame(
            $first->getKey(),
            $this->service->findUsed((int) $this->campaign->getKey(), $this->memberId, 'ORDER-1')?->getKey()
        );
        $this->assertSame('120.00', $first->fresh()->amount);
        $this->assertNotNull($first->fresh()->used_at);
    }

    public function test_record_without_order_number_creates_separate_rows(): void
    {
        $this->service->record((int) $this->campaign->getKey(), $this->memberId, null, '80.00');
        $this->service->record((int) $this->campaign->getKey(), $this->memberId, null, '90.00');

        $this->assertSame(2, CampaignUsage::query()->count());
        $this->assertSame(2, $this->service->countForMember((int) $this->campaign->getKey(), $this->memberId));
    }

    public function test_record_validates_amount_and_campaign(): void
    {
        try {
            $this->service->record((int) $this->campaign->getKey(), $this->memberId, 'ORDER-2', '-1.00');
            $this->fail('负数金额应当被拒绝');
        } catch (InvalidArgumentException) {
            $this->assertSame(0, CampaignUsage::query()->count());
        }

        $this->expectException(ModelNotFoundException::class);
        $this->service->record(9999, $this->memberId, 'ORDER-3', '100.00');
    }

    public function test_usage_counts_feed_member_limit_check(): void
    {
        $campaignService = $this->app->make(CampaignService::class);
        $campaign = $this->createCampaign([
            'rules_json' => [
                'thresholds' => [['min_amount' => '100.00', 'discount_amount' => '10.00']],
                'max_per_member' => 2,
            ],
        ]);

        $this->assertTrue($campaignService->eligible($campaign, $this->memberId, 1, [], '120.00'));
        $this->service->record((int) $campaign->getKey(), $this->memberId, 'ORDER-A', '120.00');
        $this->assertTrue($campaignService->eligible($campaign, $this->memberId, 1, [], '120.00'));
        $this->service->record((int) $campaign->getKey(), $this->memberId, 'ORDER-B', '120.00');
        $this->assertFalse($campaignService->eligible($campaign, $this->memberId, 1, [], '120.00'));
    }
}
