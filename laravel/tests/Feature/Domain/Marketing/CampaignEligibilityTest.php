<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Marketing;

use App\Domain\Marketing\Enums\CampaignScope;
use App\Domain\Marketing\Enums\CampaignStatus;
use App\Domain\Marketing\Enums\CampaignType;
use App\Domain\Marketing\Services\CampaignService;
use App\Domain\Marketing\Services\UsageService;

class CampaignEligibilityTest extends MarketingTestCase
{
    private CampaignService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(CampaignService::class);
    }

    public function test_status_and_time_window(): void
    {
        $draft = $this->createCampaign(['status' => CampaignStatus::DRAFT]);
        $this->assertFalse($this->service->eligible($draft, null, 1, [], '100.00'));

        $active = $this->service->activate((int) $draft->getKey());
        $this->assertTrue($this->service->eligible($active, null, 1, [], '100.00'));

        $future = $this->createCampaign([
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
        ]);
        $this->assertFalse($this->service->eligible($future, null, 1, [], '100.00'));

        $expired = $this->createCampaign([
            'start_at' => now()->subDays(2),
            'end_at' => now()->subDay(),
        ]);
        $this->assertFalse($this->service->eligible($expired, null, 1, [], '100.00'));

        $disabled = $this->service->disable((int) $active->getKey());
        $this->assertFalse($this->service->eligible($disabled, null, 1, [], '100.00'));
    }

    public function test_store_scope(): void
    {
        $allStores = $this->createCampaign();
        $this->assertTrue($this->service->eligible($allStores, null, 1, [], '100.00'));
        $this->assertTrue($this->service->eligible($allStores, null, null, [], '100.00'));

        $singleStore = $this->createCampaign([
            'scope' => CampaignScope::STORE,
            'store_id' => 1,
        ]);
        $this->assertTrue($this->service->eligible($singleStore, null, 1, [], '100.00'));
        $this->assertFalse($this->service->eligible($singleStore, null, 2, [], '100.00'));
        $this->assertFalse($this->service->eligible($singleStore, null, null, [], '100.00'));
    }

    public function test_sku_scope(): void
    {
        $firstSku = $this->createSku('30.00');
        $secondSku = $this->createSku('20.00');

        $scoped = $this->createCampaign(['skus' => [$firstSku]]);
        $this->assertTrue($this->service->eligible($scoped, null, 1, [$firstSku], '100.00'));
        $this->assertFalse($this->service->eligible($scoped, null, 1, [$secondSku], '100.00'));
        $this->assertFalse($this->service->eligible($scoped, null, 1, [], '100.00'));

        $allSku = $this->createCampaign();
        $this->assertTrue($this->service->eligible($allSku, null, 1, [$secondSku], '100.00'));
    }

    public function test_amount_threshold(): void
    {
        $campaign = $this->createCampaign([
            'rules_json' => [
                'thresholds' => [['min_amount' => '100.00', 'discount_amount' => '10.00']],
                'min_amount' => '80.00',
            ],
        ]);

        $this->assertFalse($this->service->eligible($campaign, null, 1, [], '79.99'));
        $this->assertTrue($this->service->eligible($campaign, null, 1, [], '80.00'));
    }

    public function test_member_usage_limit(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();
        $campaign = $this->createCampaign([
            'rules_json' => [
                'thresholds' => [['min_amount' => '100.00', 'discount_amount' => '10.00']],
                'max_per_member' => 1,
            ],
        ]);

        $this->assertTrue($this->service->eligible($campaign, $member, 1, [], '120.00'));

        $this->app->make(UsageService::class)->record((int) $campaign->getKey(), $member, 'ORDER-1', '120.00');

        $this->assertFalse($this->service->eligible($campaign, $member, 1, [], '120.00'));
        $this->assertTrue($this->service->eligible($campaign, $otherMember, 1, [], '120.00'));

        // 有每人次数限制的活动，匿名（无会员）不可用
        $this->assertFalse($this->service->eligible($campaign, null, 1, [], '120.00'));
    }

    public function test_resolve_prefers_priority_and_exclusive(): void
    {
        $sku = $this->createSku('100.00');
        $items = [$this->item($sku, '100.00')];

        $lowPriority = $this->createCampaign([
            'name' => '满减低优先',
            'priority' => 10,
            'rules_json' => ['thresholds' => [['min_amount' => '100.00', 'discount_amount' => '10.00']]],
        ]);
        $highPriority = $this->createCampaign([
            'name' => '折扣高优先',
            'type' => CampaignType::PERCENT,
            'priority' => 20,
            'rules_json' => ['percent' => 90, 'max_discount' => '5.00'],
        ]);

        $result = $this->service->resolve([$lowPriority, $highPriority], $items, null, 1);

        $this->assertSame(
            [(int) $highPriority->getKey()],
            array_map(static fn ($campaign): int => (int) $campaign->getKey(), $result['campaigns'])
        );
        $this->assertSame('5.00', $result['discount']);
        $this->assertFalse($result['stacked']);
    }

    public function test_resolve_stacks_across_stack_groups(): void
    {
        $sku = $this->createSku('100.00');
        $items = [$this->item($sku, '100.00')];

        $primary = $this->createCampaign([
            'name' => '主活动',
            'type' => CampaignType::PERCENT,
            'priority' => 20,
            'rules_json' => ['percent' => 90, 'stackable' => true, 'stack_group' => 'main'],
        ]);
        // 与主活动同组：即使可叠加也应互斥
        $sameGroup = $this->createCampaign([
            'name' => '同组活动',
            'priority' => 15,
            'rules_json' => [
                'thresholds' => [['min_amount' => '100.00', 'discount_amount' => '8.00']],
                'stackable' => true,
                'stack_group' => 'main',
            ],
        ]);
        $otherGroup = $this->createCampaign([
            'name' => '跨组活动',
            'priority' => 10,
            'rules_json' => [
                'thresholds' => [['min_amount' => '100.00', 'discount_amount' => '5.00']],
                'stackable' => true,
                'stack_group' => 'extra',
            ],
        ]);

        $result = $this->service->resolve([$primary, $sameGroup, $otherGroup], $items, null, 1);
        $ids = array_map(static fn ($campaign): int => (int) $campaign->getKey(), $result['campaigns']);

        $this->assertCount(2, $ids);
        $this->assertSame([(int) $primary->getKey(), (int) $otherGroup->getKey()], $ids);
        $this->assertSame('15.00', $result['discount']);
        $this->assertTrue($result['stacked']);
    }
}
