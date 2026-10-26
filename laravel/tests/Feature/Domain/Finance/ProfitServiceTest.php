<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Finance;

use App\Domain\Finance\Models\ProfitDaily;
use Illuminate\Support\Facades\DB;

class ProfitServiceTest extends FinanceTestCase
{
    public function test_compute_uses_gross_profit_and_percentage_margin(): void
    {
        $result = $this->profitService()->compute(250.00, 150.00);

        $this->assertSame(100.0, $result['gross_profit']);
        $this->assertSame(40.0, $result['gross_margin']);

        $zeroRevenue = $this->profitService()->compute(0.0, 50.00);
        $this->assertSame(-50.0, $zeroRevenue['gross_profit']);
        $this->assertSame(0.0, $zeroRevenue['gross_margin']);
    }

    public function test_recalc_uses_revenue_net_amount_and_cost_total(): void
    {
        DB::table('revenue_daily')->insert([
            'store_id' => 1,
            'date' => '2026-10-20',
            'order_count' => 3,
            'gross_amount' => 300.00,
            'discount_amount' => 30.00,
            'refund_amount' => 50.00,
            'net_amount' => 250.00,
            'payment_fee' => 1.00,
        ]);
        DB::table('cost_daily')->insert([
            'store_id' => 1,
            'date' => '2026-10-20',
            'material_cost' => 100.00,
            'labor_cost' => 40.00,
            'other_cost' => 10.00,
        ]);

        $profit = $this->profitService()->recalc(1, '2026-10-20');

        $this->assertNotNull($profit);
        $this->assertSame(250.0, (float) $profit->revenue);
        $this->assertSame(150.0, (float) $profit->cost);
        $this->assertSame(100.0, (float) $profit->gross_profit);
        $this->assertSame(40.0, (float) $profit->gross_margin);
    }

    public function test_cost_only_day_has_negative_profit(): void
    {
        DB::table('cost_daily')->insert([
            'store_id' => 1,
            'date' => '2026-10-20',
            'material_cost' => 0,
            'labor_cost' => 80.00,
            'other_cost' => 0,
        ]);

        $profit = $this->profitService()->recalc(1, '2026-10-20');

        $this->assertNotNull($profit);
        $this->assertSame(0.0, (float) $profit->revenue);
        $this->assertSame(80.0, (float) $profit->cost);
        $this->assertSame(-80.0, (float) $profit->gross_profit);
        $this->assertSame(0.0, (float) $profit->gross_margin);
    }

    public function test_recalc_returns_null_when_no_revenue_or_cost_row(): void
    {
        $this->assertNull($this->profitService()->recalc(1, '2026-10-20'));
        $this->assertSame(0, ProfitDaily::query()->count());
    }

    public function test_recalc_is_idempotent_and_updates_same_row(): void
    {
        DB::table('revenue_daily')->insert([
            'store_id' => 1,
            'date' => '2026-10-20',
            'order_count' => 1,
            'gross_amount' => 100.00,
            'discount_amount' => 0,
            'refund_amount' => 0,
            'net_amount' => 100.00,
            'payment_fee' => 0,
        ]);
        DB::table('cost_daily')->insert([
            'store_id' => 1,
            'date' => '2026-10-20',
            'material_cost' => 30.00,
            'labor_cost' => 0,
            'other_cost' => 0,
        ]);

        $first = $this->profitService()->recalc(1, '2026-10-20');
        $this->assertNotNull($first);

        DB::table('cost_daily')->where('store_id', 1)->where('date', '2026-10-20')->update(['labor_cost' => 30.00]);

        $second = $this->profitService()->recalc(1, '2026-10-20');

        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ProfitDaily::query()->count());
        $this->assertSame(60.0, (float) $second->cost);
        $this->assertSame(40.0, (float) $second->gross_profit);
        $this->assertSame(40.0, (float) $second->gross_margin);
    }
}
