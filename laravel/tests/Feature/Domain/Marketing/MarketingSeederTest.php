<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Marketing;

use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\CampaignSku;
use App\Domain\Marketing\Models\CampaignUsage;
use App\Domain\Marketing\Models\MemberPriceRule;
use App\Domain\Marketing\Models\MemberTag;
use Database\Seeders\MarketingSeeder;

class MarketingSeederTest extends MarketingTestCase
{
    public function test_seeder_is_idempotent(): void
    {
        $this->seed(MarketingSeeder::class);
        $this->seed(MarketingSeeder::class);

        $this->assertSame(3, MemberTag::query()->count());
        $this->assertSame(3, MemberPriceRule::query()->count());
        $this->assertSame(4, Campaign::query()->count());
        $this->assertSame(2, CampaignSku::query()->count());
        $this->assertSame(1, CampaignUsage::query()->count());
    }

    public function test_seeder_creates_active_campaigns(): void
    {
        $this->seed(MarketingSeeder::class);

        $this->assertSame(4, Campaign::query()->where('status', 'active')->count());
        $this->assertTrue(MemberTag::query()->where('name', '新客')->exists());

        $gift = Campaign::query()->where('type', 'gift')->firstOrFail();
        $rules = $gift->rules_json ?? [];
        $this->assertSame(9, (int) $rules['gift_sku_id']);
    }
}
