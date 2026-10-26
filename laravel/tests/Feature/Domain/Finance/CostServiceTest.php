<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Finance;

use App\Domain\Finance\Models\CostDaily;
use Illuminate\Support\Facades\DB;

class CostServiceTest extends FinanceTestCase
{
    public function test_explicit_cost_components_are_used(): void
    {
        $result = $this->costService()->aggregate(1, '2026-10-20', [
            'material_cost' => 50.00,
            'labor_cost' => 20.00,
            'other_cost' => 5.00,
        ]);

        $this->assertSame(50.0, $result['material_cost']);
        $this->assertSame(20.0, $result['labor_cost']);
        $this->assertSame(5.0, $result['other_cost']);
        $this->assertSame(75.0, $result['total_cost']);
        $this->assertTrue($result['has_data']);
    }

    public function test_material_cost_is_read_from_stock_ledger_consume_rows(): void
    {
        $warehouseId = DB::table('warehouses')->insertGetId([
            'store_id' => 1,
            'name' => '测试门店仓',
            'type' => 'store',
            'status' => 'active',
        ]);

        foreach ([12.50, 7.50] as $index => $amount) {
            DB::table('stock_ledger')->insert([
                'warehouse_id' => $warehouseId,
                'item_type' => 'material',
                'item_id' => 1,
                'direction' => 'out',
                'biz_type' => 'consume',
                'biz_id' => 'ORDER-'.$index,
                'qty' => 1,
                'unit_cost' => $amount,
                'amount' => $amount,
                'balance_after' => 10,
                'created_at' => '2026-10-20 0'.($index + 1).':00:00',
            ]);
        }

        // 其他日期/其他门店的流水不计入
        DB::table('stock_ledger')->insert([
            'warehouse_id' => $warehouseId,
            'item_type' => 'material',
            'item_id' => 1,
            'direction' => 'out',
            'biz_type' => 'consume',
            'biz_id' => 'ORDER-OTHER',
            'qty' => 1,
            'unit_cost' => 99.00,
            'amount' => 99.00,
            'balance_after' => 9,
            'created_at' => '2026-10-21 09:00:00',
        ]);

        $result = $this->costService()->aggregate(1, '2026-10-20');

        $this->assertSame(20.0, $result['material_cost']);
        $this->assertSame(20.0, $result['total_cost']);
        $this->assertTrue($result['has_data']);
    }

    public function test_material_cost_can_be_derived_from_cost_cards_with_sold_qty(): void
    {
        DB::table('cost_cards')->insert([
            'product_sku_id' => 5,
            'version' => 1,
            'unit_cost' => 3.50,
            'calc_at' => '2026-10-20 00:00:00',
        ]);

        $result = $this->costService()->aggregate(1, '2026-10-20', [
            'sold_sku_qty' => [5 => 10],
        ]);

        $this->assertSame(35.0, $result['material_cost']);
        $this->assertTrue($result['has_data']);
    }

    public function test_fallback_material_cost_is_used_when_no_source(): void
    {
        $result = $this->costService()->aggregate(1, '2026-10-20', [
            'fallback_material_cost' => 33.33,
        ]);

        $this->assertSame(33.33, $result['material_cost']);
        $this->assertSame(33.33, $result['total_cost']);
        $this->assertTrue($result['has_data']);
    }

    public function test_recalc_returns_null_and_writes_nothing_when_no_cost_data(): void
    {
        $this->assertNull($this->costService()->recalc(1, '2026-10-20'));
        $this->assertSame(0, CostDaily::query()->count());
    }

    public function test_recalc_persists_and_is_idempotent(): void
    {
        $row = $this->costService()->recalc(1, '2026-10-20', [
            'material_cost' => 10.00,
            'labor_cost' => 100.00,
        ]);

        $this->assertNotNull($row);
        $this->assertSame(110.0, (float) $row->total());

        // 重算：同门店同日一行；后面只传人工成本时原料成本会按本次口径覆盖为 0
        $this->costService()->recalc(1, '2026-10-20', ['labor_cost' => 120.00]);

        $this->assertSame(1, CostDaily::query()->count());
        $fresh = CostDaily::query()->firstOrFail();
        $this->assertSame(0.0, (float) $fresh->material_cost);
        $this->assertSame(120.0, (float) $fresh->labor_cost);
        $this->assertSame(120.0, $fresh->total());
    }

    public function test_cost_only_day_creates_cost_row(): void
    {
        $row = $this->costService()->recalc(1, '2026-10-20', ['labor_cost' => 100.00]);

        $this->assertNotNull($row);
        $this->assertSame(100.0, $row->total());
        $this->assertSame(1, CostDaily::query()->count());
    }
}
