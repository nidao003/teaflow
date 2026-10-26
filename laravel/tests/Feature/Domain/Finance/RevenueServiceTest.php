<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Finance;

use App\Domain\Finance\Models\RevenueDaily;

class RevenueServiceTest extends FinanceTestCase
{
    public function test_net_revenue_is_successful_payments_minus_refunds(): void
    {
        $day = '2026-10-20';

        // 订单 A：应收 100、实付 90（折扣 10），关联支付成功 90，当日退款 20
        $this->makeOrder('ORD-A', 1, 100.00, 90.00, '2026-10-20 09:00:00', '2026-10-20 09:10:00');
        $this->makePayment('ORD-A', 1, 'wechat', 90.00, '2026-10-20 09:10:00');
        $this->makeRefund('ORD-A', 20.00, '2026-10-20 12:00:00');

        // 订单 B：全额支付 50
        $this->makeOrder('ORD-B', 1, 50.00, 50.00, '2026-10-20 10:00:00', '2026-10-20 10:05:00');
        $this->makePayment('ORD-B', 1, 'alipay', 50.00, '2026-10-20 10:05:00');

        // 干扰数据：其他门店、失败支付、处理中退款
        $this->makeOrder('ORD-OTHER', 2, 500.00, 500.00, '2026-10-20 11:00:00', '2026-10-20 11:05:00');
        $this->makePayment('ORD-OTHER', 2, 'wechat', 500.00, '2026-10-20 11:05:00');
        $this->makePayment('ORD-A', 1, 'wechat', 30.00, '2026-10-20 13:00:00', 'failed');
        $this->makeRefund('ORD-B', 5.00, '2026-10-20 14:00:00', 'processing');

        $result = $this->revenueService()->aggregate(1, $day);

        $this->assertSame(2, $result['order_count']);
        $this->assertSame(140.0, $result['gross_amount']);
        $this->assertSame(10.0, $result['discount_amount']);
        $this->assertSame(20.0, $result['refund_amount']);
        $this->assertSame(120.0, $result['net_amount']);
        $this->assertSame(0.0, $result['payment_fee']);
        $this->assertTrue($result['has_data']);
    }

    public function test_refund_is_attributed_to_refund_success_day(): void
    {
        $this->makeOrder('ORD-R', 1, 100.00, 90.00, '2026-10-20 09:00:00', '2026-10-20 09:10:00');
        $this->makePayment('ORD-R', 1, 'wechat', 90.00, '2026-10-20 09:10:00');
        $this->makeRefund('ORD-R', 20.00, '2026-10-21 10:00:00');

        $day1 = $this->revenueService()->aggregate(1, '2026-10-20');
        $this->assertSame(90.0, $day1['gross_amount']);
        $this->assertSame(0.0, $day1['refund_amount']);
        $this->assertSame(90.0, $day1['net_amount']);
        $this->assertSame(1, $day1['order_count']);

        // 退款归属退款成功日：次日净收入为负，且当日没有订单
        $day2 = $this->revenueService()->aggregate(1, '2026-10-21');
        $this->assertSame(0.0, $day2['gross_amount']);
        $this->assertSame(20.0, $day2['refund_amount']);
        $this->assertSame(-20.0, $day2['net_amount']);
        $this->assertSame(0, $day2['order_count']);
        $this->assertTrue($day2['has_data']);
    }

    public function test_payment_fee_can_be_passed_or_derived_from_rate(): void
    {
        $this->makeOrder('ORD-F', 1, 1000.00, 1000.00, '2026-10-20 09:00:00', '2026-10-20 09:05:00');
        $this->makePayment('ORD-F', 1, 'wechat', 1000.00, '2026-10-20 09:05:00');

        $this->assertSame(2.50, $this->revenueService()->aggregate(1, '2026-10-20', ['payment_fee' => 2.50])['payment_fee']);
        $this->assertSame(6.00, $this->revenueService()->aggregate(1, '2026-10-20', ['fee_rate' => 0.006])['payment_fee']);
        $this->assertSame(0.0, $this->revenueService()->aggregate(1, '2026-10-20')['payment_fee']);
    }

    public function test_cancelled_orders_are_not_counted_but_keep_payment_amount(): void
    {
        $this->makeOrder('ORD-OK', 1, 100.00, 100.00, '2026-10-20 09:00:00');
        $this->makeOrder('ORD-CANCEL', 1, 100.00, 100.00, '2026-10-20 10:00:00', null, 'cancelled');

        $result = $this->revenueService()->aggregate(1, '2026-10-20');

        $this->assertSame(1, $result['order_count']);
    }

    public function test_recalc_persists_and_recomputes_without_duplicates(): void
    {
        $this->makeOrder('ORD-RC', 1, 100.00, 90.00, '2026-10-20 09:00:00', '2026-10-20 09:10:00');
        $this->makePayment('ORD-RC', 1, 'wechat', 90.00, '2026-10-20 09:10:00');
        $this->makeRefund('ORD-RC', 10.00, '2026-10-20 12:00:00');

        $row = $this->revenueService()->recalc(1, '2026-10-20');
        $this->assertInstanceOf(RevenueDaily::class, $row);
        $this->assertSame(90.0, (float) $row->gross_amount);
        $this->assertSame(10.0, (float) $row->refund_amount);
        $this->assertSame(80.0, (float) $row->net_amount);

        $this->revenueService()->recalc(1, '2026-10-20');
        $this->assertSame(1, RevenueDaily::query()->count());

        // 新增同日订单+支付后重算：同一行更新，不新增
        $this->makeOrder('ORD-RC2', 1, 30.00, 20.00, '2026-10-20 15:00:00', '2026-10-20 15:05:00');
        $this->makePayment('ORD-RC2', 1, 'wechat', 20.00, '2026-10-20 15:05:00');

        $updated = $this->revenueService()->recalc(1, '2026-10-20');

        $this->assertNotNull($updated);
        $this->assertSame($row->id, $updated->id);
        $this->assertSame(1, RevenueDaily::query()->count());
        $this->assertSame(110.0, (float) $updated->gross_amount);
        $this->assertSame(100.0, (float) $updated->net_amount);
        $this->assertSame(2, (int) $updated->order_count);
    }

    public function test_recalc_returns_null_and_writes_nothing_when_no_data(): void
    {
        $this->assertNull($this->revenueService()->recalc(1, '2026-10-25'));
        $this->assertSame(0, RevenueDaily::query()->count());
    }

    public function test_order_only_day_still_has_data(): void
    {
        // 有订单但还没支付：订单数要计入，收入为 0
        $this->makeOrder('ORD-NOPAY', 1, 100.00, 0.00, '2026-10-20 09:00:00');

        $result = $this->revenueService()->aggregate(1, '2026-10-20');

        $this->assertSame(1, $result['order_count']);
        $this->assertSame(0.0, $result['gross_amount']);
        $this->assertTrue($result['has_data']);
        $this->assertNotNull($this->revenueService()->recalc(1, '2026-10-20'));
    }
}
