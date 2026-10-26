<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Order;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PayStatus;
use App\Domain\Order\Enums\RefundStatus;
use App\Domain\Order\Exceptions\InvalidRefundStatusException;
use App\Domain\Order\Exceptions\OrderNotPaidException;
use App\Domain\Order\Exceptions\RefundExceedsPaidAmountException;
use App\Domain\Order\Models\Refund;
use App\Domain\Order\Services\PaymentService;
use App\Domain\Order\Services\RefundService;
use Illuminate\Support\Facades\DB;

class RefundServiceTest extends OrderTestCase
{
    private function refunds(): RefundService
    {
        return app(RefundService::class);
    }

    public function test_partial_refunds_accumulate_and_update_order_amounts(): void
    {
        $order = $this->makePaidOrder('100.00');
        $orderNumber = (string) $order['order_number'];
        $service = $this->refunds();

        $first = $service->requestRefund($orderNumber, '30.00', 'RF-1', '少糖', 9);

        $this->assertSame(RefundStatus::CREATED, $first->status);
        $this->assertSame('30.00', $first->amount);
        $this->assertSame(OrderStatus::REFUNDING->value, $this->orderRow($orderNumber)['order_status']);

        $approved = $service->approve('RF-1', 8, 7);
        $this->assertSame(RefundStatus::PROCESSING, $approved->status);
        $this->assertSame(8, (int) $approved->approved_by);

        $refund = $service->execute('RF-1', 'WXRF-1', 8);
        $this->assertSame(RefundStatus::SUCCESS, $refund->status);
        $this->assertSame('WXRF-1', $refund->channel_refund_id);
        $this->assertNotNull($refund->refunded_at);

        // 退款成功后 orders 金额必须正确：已退 30 / 剩余 70。
        $row = $this->orderRow($orderNumber);
        $this->assertAmount(30.0, $row['refunded_amount']);
        $this->assertAmount(70.0, $row['real_amount']);
        $this->assertSame(OrderStatus::PARTIALLY_REFUNDED->value, $row['order_status']);
        $this->assertSame(PayStatus::COMPLETED->value, $row['pay_status']);

        // 第二笔部分退款（额度 70）应可继续申请并执行。
        $service->requestRefund($orderNumber, '70.00', 'RF-2');
        $service->approve('RF-2', 8);
        $service->execute('RF-2', 'WXRF-2');

        $row = $this->orderRow($orderNumber);
        $this->assertAmount(100.0, $row['refunded_amount']);
        $this->assertAmount(0.0, $row['real_amount']);
        $this->assertSame(OrderStatus::REFUNDED->value, $row['order_status']);

        $this->assertSame(
            2,
            DB::table('order_events')->where('order_number', $orderNumber)->where('event', 'refund_succeeded')->count()
        );

        // 已全额退款后，再申请 0.01 必须被拒绝。
        $this->expectException(RefundExceedsPaidAmountException::class);
        $service->requestRefund($orderNumber, '0.01', 'RF-3');
    }

    public function test_pending_refunds_consume_the_limit(): void
    {
        $order = $this->makePaidOrder('100.00');
        $orderNumber = (string) $order['order_number'];
        $service = $this->refunds();

        // 60 处于 created（处理中）状态，已占用额度。
        $service->requestRefund($orderNumber, '60.00', 'RF-PENDING');

        $this->expectException(RefundExceedsPaidAmountException::class);
        $service->requestRefund($orderNumber, '50.00', 'RF-OVER');
    }

    public function test_single_refund_over_paid_amount_is_rejected(): void
    {
        $order = $this->makePaidOrder('100.00');
        $orderNumber = (string) $order['order_number'];
        $service = $this->refunds();

        try {
            $service->requestRefund($orderNumber, '100.01', 'RF-OVER-ONE');
            $this->fail('超额退款应被拒绝');
        } catch (RefundExceedsPaidAmountException $e) {
            $this->assertStringContainsString('100.00', $e->getMessage());
        }

        $this->assertSame(0, Refund::query()->count());
        $this->assertSame(OrderStatus::PAID->value, $this->orderRow($orderNumber)['order_status']);
    }

    public function test_refund_requires_paid_order(): void
    {
        $order = $this->makeOrder();

        $this->expectException(OrderNotPaidException::class);
        $this->refunds()->requestRefund((string) $order['order_number'], '10.00', 'RF-UNPAID');
    }

    public function test_execute_requires_approval_first(): void
    {
        $order = $this->makePaidOrder('100.00');
        $orderNumber = (string) $order['order_number'];
        $service = $this->refunds();

        $service->requestRefund($orderNumber, '10.00', 'RF-NO-APPROVE');

        $this->expectException(InvalidRefundStatusException::class);
        $service->execute('RF-NO-APPROVE', 'WXRF-X');
    }

    public function test_duplicate_refund_no_is_idempotent_and_reserves_limit_once(): void
    {
        $order = $this->makePaidOrder('100.00');
        $orderNumber = (string) $order['order_number'];
        $service = $this->refunds();

        $first = $service->requestRefund($orderNumber, '10.00', 'RF-DUP');
        $again = $service->requestRefund($orderNumber, '10.00', 'RF-DUP');

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Refund::query()->count());

        // 额度只占用一次：10（处理中）+ 90 可申请，95 必须拒绝。
        $service->requestRefund($orderNumber, '90.00', 'RF-DUP-2');

        $this->expectException(RefundExceedsPaidAmountException::class);
        $service->requestRefund($orderNumber, '95.00', 'RF-DUP-3');
    }

    public function test_execute_is_idempotent(): void
    {
        $order = $this->makePaidOrder('100.00');
        $orderNumber = (string) $order['order_number'];
        $service = $this->refunds();

        $service->requestRefund($orderNumber, '40.00', 'RF-IDEM');
        $service->approve('RF-IDEM', 8);
        $service->execute('RF-IDEM', 'WXRF-IDEM');
        $service->execute('RF-IDEM', 'WXRF-IDEM');

        $row = $this->orderRow($orderNumber);
        $this->assertAmount(40.0, $row['refunded_amount']);
        $this->assertAmount(60.0, $row['real_amount']);
        $this->assertSame(1, Refund::query()->count());
    }

    public function test_refund_can_be_requested_after_payment_success(): void
    {
        $order = $this->makeOrder(['total_amount' => '30.00', 'real_amount' => '30.00']);
        $orderNumber = (string) $order['order_number'];

        $payment = app(PaymentService::class)
            ->createPayment($orderNumber, 'wechat', 'idem-refund-flow');
        app(PaymentService::class)
            ->handleCallback('wechat', 'notify-refund-flow', $payment->out_trade_no, 'wx-refund-flow', '30.00', true);

        $refund = $this->refunds()->requestRefund($orderNumber, '10.00', 'RF-AFTER-PAY', '撒糖错误', 9);
        $this->assertSame(RefundStatus::CREATED, $refund->status);
        $this->assertNotNull($refund->payment_id);
        $this->assertSame((int) $payment->id, (int) $refund->payment_id);

        $this->refunds()->approve('RF-AFTER-PAY', 8);
        $this->refunds()->execute('RF-AFTER-PAY', 'WXRF-AFTER-PAY');

        $row = $this->orderRow($orderNumber);
        $this->assertAmount(20.0, $row['real_amount']);
        $this->assertAmount(10.0, $row['refunded_amount']);
        $this->assertSame(OrderStatus::PARTIALLY_REFUNDED->value, $row['order_status']);
    }
}
