<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Marketing;

use App\Domain\Marketing\Services\MemberPriceService;
use InvalidArgumentException;

class MemberPriceServiceTest extends MarketingTestCase
{
    private MemberPriceService $service;

    private int $skuId;

    private int $levelId;

    private int $otherLevelId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(MemberPriceService::class);
        $this->skuId = $this->createSku('30.00');
        $this->levelId = $this->createLevel();
        $this->otherLevelId = $this->createLevel();
    }

    public function test_no_rule_returns_original_price(): void
    {
        $this->assertSame('30.00', $this->service->originalPrice($this->skuId));
        $this->assertSame('30.00', $this->service->effectivePrice($this->skuId, $this->levelId));
        $this->assertSame('30.00', $this->service->effectivePrice($this->skuId, null));
    }

    public function test_member_price_by_level(): void
    {
        $this->service->setPrice($this->skuId, $this->levelId, '25.00');

        $this->assertSame('25.00', $this->service->effectivePrice($this->skuId, $this->levelId));
        $this->assertSame('30.00', $this->service->effectivePrice($this->skuId, $this->otherLevelId));
    }

    public function test_effective_date_window(): void
    {
        $future = now()->addDay()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $this->service->setPrice($this->skuId, $this->levelId, '25.00', $future);
        $this->assertSame('30.00', $this->service->effectivePrice($this->skuId, $this->levelId));
        $this->assertSame(
            '25.00',
            $this->service->effectivePrice($this->skuId, $this->levelId, now()->addDays(2))
        );

        $this->service->setPrice(
            $this->skuId,
            $this->levelId,
            '20.00',
            now()->subDays(30)->toDateString(),
            $yesterday
        );

        $this->assertSame('30.00', $this->service->effectivePrice($this->skuId, $this->levelId));
    }

    public function test_lowest_price_wins_among_active_rules(): void
    {
        $this->service->setPrice($this->skuId, $this->levelId, '25.00', now()->subDays(100)->toDateString());
        $this->service->setPrice($this->skuId, $this->levelId, '22.00', now()->subDays(10)->toDateString());

        $this->assertSame('22.00', $this->service->effectivePrice($this->skuId, $this->levelId));
    }

    public function test_member_price_never_above_original_price(): void
    {
        $this->service->setPrice($this->skuId, $this->levelId, '40.00');

        $this->assertSame('30.00', $this->service->effectivePrice($this->skuId, $this->levelId));
    }

    public function test_price_for_member_resolves_level(): void
    {
        $this->service->setPrice($this->skuId, $this->levelId, '25.00');

        $vip = $this->createMember($this->levelId);
        $normal = $this->createMember($this->otherLevelId);
        $anonymous = $this->createMember(null);

        $this->assertSame('25.00', $this->service->priceForMember($vip, $this->skuId));
        $this->assertSame('30.00', $this->service->priceForMember($normal, $this->skuId));
        $this->assertSame('30.00', $this->service->priceForMember($anonymous, $this->skuId));
    }

    public function test_unknown_sku_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->effectivePrice(9999, $this->levelId);
    }
}
