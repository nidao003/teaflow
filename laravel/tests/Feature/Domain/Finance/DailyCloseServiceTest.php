<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Finance;

use App\Domain\Finance\Models\CostDaily;
use App\Domain\Finance\Models\ProfitDaily;
use App\Domain\Finance\Models\RevenueDaily;

class DailyCloseServiceTest extends FinanceTestCase
{
    private function seedDayRevenue(string $day, float $paidAmount, float $refundAmount = 0.0): void
    {
        $this->makeOrder('ORD-CLOSE', 1, 100.00, $paidAmount, $day.' 09:00:00', $day.' 09:05:00');
        $this->makePayment('ORD-CLOSE', 1, 'wechat', $paidAmount, $day.' 09:05:00');

        if ($refundAmount > 0) {
            $this->makeRefund('ORD-CLOSE', $refundAmount, $day.' 12:00:00');
        }
    }

    public function test_close_generates_revenue_cost_and_profit_rows(): void
    {
        $day = '2026-10-20';
        $this->seedDayRevenue($day, 90.00, 10.00);

        $result = $this->dailyCloseService()->close(1, $day, [
            'material_cost' => 30.00,
            'labor_cost' => 20.00,
            'other_cost' => 5.00,
        ]);

        $this->assertNotNull($result);
        $this->assertSame(1, $result['store_id']);
        $this->assertSame($day, $result['date']);

        $this->assertInstanceOf(RevenueDaily::class, $result['revenue']);
        $this->assertSame(80.0, (float) $result['revenue']->net_amount);

        $this->assertInstanceOf(CostDaily::class, $result['cost']);
        $this->assertSame(55.0, $result['cost']->total());

        $this->assertInstanceOf(ProfitDaily::class, $result['profit']);
        $this->assertSame(80.0, (float) $result['profit']->revenue);
        $this->assertSame(55.0, (float) $result['profit']->cost);
        $this->assertSame(25.0, (float) $result['profit']->gross_profit);
        // 25 / 80 * 100 = 31.25%
        $this->assertSame(31.25, (float) $result['profit']->gross_margin);

        $this->assertSame(1, RevenueDaily::query()->count());
        $this->assertSame(1, CostDaily::query()->count());
        $this->assertSame(1, ProfitDaily::query()->count());
    }

    public function test_close_is_idempotent_and_recomputes_after_new_payments(): void
    {
        $day = '2026-10-20';
        $this->seedDayRevenue($day, 90.00, 10.00);

        $first = $this->dailyCloseService()->close(1, $day, ['labor_cost' => 20.00]);
        $this->assertNotNull($first);
        $revenueId = $first['revenue']?->id;
        $profitId = $first['profit']?->id;

        // 同日新增订单与支付后重跑日结：同一行更新，不重复插入
        $this->makeOrder('ORD-CLOSE-2', 1, 20.00, 20.00, $day.' 15:00:00', $day.' 15:05:00');
        $this->makePayment('ORD-CLOSE-2', 1, 'wechat', 20.00, $day.' 15:05:00');

        $second = $this->dailyCloseService()->close(1, $day, ['labor_cost' => 20.00]);

        $this->assertNotNull($second);
        $this->assertSame($revenueId, $second['revenue']?->id);
        $this->assertSame($profitId, $second['profit']?->id);
        $this->assertSame(1, RevenueDaily::query()->count());
        $this->assertSame(1, CostDaily::query()->count());
        $this->assertSame(1, ProfitDaily::query()->count());

        $this->assertSame(110.0, (float) $second['revenue']?->gross_amount);
        $this->assertSame(100.0, (float) $second['revenue']?->net_amount);
        $this->assertSame(80.0, (float) $second['profit']?->gross_profit);
    }

    public function test_close_returns_null_and_writes_nothing_on_empty_day(): void
    {
        $this->assertNull($this->dailyCloseService()->close(1, '2026-10-25'));

        $this->assertSame(0, RevenueDaily::query()->count());
        $this->assertSame(0, CostDaily::query()->count());
        $this->assertSame(0, ProfitDaily::query()->count());
    }

    public function test_cost_only_day_generates_negative_profit(): void
    {
        $result = $this->dailyCloseService()->close(1, '2026-10-20', ['labor_cost' => 100.00]);

        $this->assertNotNull($result);
        $this->assertNull($result['revenue']);
        $this->assertNotNull($result['cost']);
        $this->assertSame(100.0, $result['cost']->total());
        $this->assertNotNull($result['profit']);
        $this->assertSame(0.0, (float) $result['profit']->revenue);
        $this->assertSame(-100.0, (float) $result['profit']->gross_profit);
        $this->assertSame(0.0, (float) $result['profit']->gross_margin);
    }

    public function test_close_range_skips_days_without_data(): void
    {
        $this->seedDayRevenue('2026-10-20', 90.00);
        $this->seedDayRevenue('2026-10-22', 50.00);

        $results = $this->dailyCloseService()->closeRange(1, '2026-10-20', '2026-10-22');

        $this->assertCount(2, $results);
        $this->assertSame('2026-10-20', $results[0]['date']);
        $this->assertSame('2026-10-22', $results[1]['date']);
        $this->assertSame(2, RevenueDaily::query()->count());
    }
}
