<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Order;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PaymentStatus;
use App\Domain\Order\Enums\PayStatus;
use App\Domain\Order\Exceptions\DuplicateTransactionException;
use App\Domain\Order\Exceptions\OrderAlreadyPaidException;
use App\Domain\Order\Exceptions\PaymentAmountMismatchException;
use App\Domain\Order\Exceptions\PaymentNotPayableException;
use App\Domain\Order\Exceptions\UnverifiedCallbackException;
use App\Domain\Order\Models\Payment;
use App\Domain\Order\Services\PaymentService;
use Illuminate\Support\Facades\DB;

class PaymentServiceTest extends OrderTestCase
{
    private function payments(): PaymentService
    {
        return app(PaymentService::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{0: array<string, mixed>, 1: Payment}
     */
    private function makePayment(array $attributes = [], string $idempotencyKey = 'idem-default'): array
    {
        $order = $this->makeOrder($attributes);
        $payment = $this->payments()->createPayment((string) $order['order_number'], 'wechat', $idempotencyKey);

        return [$order, $payment];
    }

    public function test_create_payment_takes_amount_from_server_order_and_is_idempotent(): void
    {
        $order = $this->makeOrder(['total_amount' => '58.50', 'real_amount' => '58.50']);
        $orderNumber = (string) $order['order_number'];
        $service = $this->payments();

        $first = $service->createPayment($orderNumber, 'wechat', 'idem-create-1');

        // 金额来自服务端订单，不接受客户端传入金额。
        $this->assertSame('58.50', $first->amount);
        $this->assertSame(PaymentStatus::CREATED, $first->status);
        $this->assertSame('wechat', $first->channel->value);
        $this->assertSame($orderNumber, $first->order_number);
        $this->assertNotSame('', $first->out_trade_no);
        $this->assertSame(1, (int) $first->store_id);

        // 幂等：同 key 重复创建返回同一条支付单。
        $again = $service->createPayment($orderNumber, 'wechat', 'idem-create-1');
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, Payment::query()->count());

        // 换 key 视为重试，允许新建支付单（金额仍取订单）。
        $other = $service->createPayment($orderNumber, 'alipay', 'idem-create-2');
        $this->assertNotSame($first->id, $other->id);
        $this->assertSame('58.50', $other->amount);
        $this->assertSame(2, Payment::query()->count());
    }

    public function test_paid_order_cannot_create_new_payment(): void
    {
        $order = $this->makePaidOrder();

        $this->expectException(OrderAlreadyPaidException::class);

        $this->payments()->createPayment((string) $order['order_number'], 'wechat', 'idem-paid');
    }

    public function test_callback_success_updates_payment_order_and_event(): void
    {
        [$order, $payment] = $this->makePayment(['total_amount' => '30.00', 'real_amount' => '30.00'], 'idem-cb-1');
        $orderNumber = (string) $order['order_number'];

        $callback = $this->payments()->handleCallback(
            'wechat',
            'notify-1001',
            $payment->out_trade_no,
            'wx-txn-1001',
            '30.00',
            true,
            ['result_code' => 'SUCCESS', 'openid' => 'o-test']
        );

        $this->assertSame('notify-1001', $callback->notify_id);
        $this->assertTrue($callback->verified);
        $this->assertNotNull($callback->processed_at);

        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, $payment->status);
        $this->assertSame('wx-txn-1001', $payment->transaction_id);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame(['result_code' => 'SUCCESS', 'openid' => 'o-test'], $payment->raw_notify);

        $row = $this->orderRow($orderNumber);
        $this->assertSame(PayStatus::COMPLETED->value, $row['pay_status']);
        $this->assertAmount(30.0, $row['paid_amount']);
        $this->assertAmount(30.0, $row['real_amount']);
        $this->assertSame(OrderStatus::PAID->value, $row['order_status']);
        $this->assertNotNull($row['paid_at']);

        $event = DB::table('order_events')
            ->where('order_number', $orderNumber)
            ->where('event', 'payment_succeeded')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame(OrderStatus::PENDING_PAYMENT->value, $event->from_status);
        $this->assertSame(OrderStatus::PAID->value, $event->to_status);
        $payload = json_decode((string) $event->payload, true);
        $this->assertSame('wx-txn-1001', $payload['transaction_id']);
        $this->assertSame('notify-1001', (string) DB::table('payment_callbacks')->where('notify_id', 'notify-1001')->value('notify_id'));
    }

    public function test_duplicate_callback_is_idempotent(): void
    {
        [$order, $payment] = $this->makePayment();
        $orderNumber = (string) $order['order_number'];
        $service = $this->payments();

        $service->handleCallback('wechat', 'notify-dup', $payment->out_trade_no, 'wx-txn-dup', '30.00', true);
        // 完全相同的重复通知
        $service->handleCallback('wechat', 'notify-dup', $payment->out_trade_no, 'wx-txn-dup', '30.00', true);
        // 渠道换 notify_id 但同一笔交易：同样不重复入账
        $service->handleCallback('wechat', 'notify-dup-2', $payment->out_trade_no, 'wx-txn-dup', '30.00', true);

        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS, $payment->status);
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(2, DB::table('payment_callbacks')->count());

        $row = $this->orderRow($orderNumber);
        $this->assertAmount(30.0, $row['paid_amount']);
        $this->assertAmount(30.0, $row['real_amount']);

        $this->assertSame(1, DB::table('order_events')->where('order_number', $orderNumber)->where('event', 'payment_succeeded')->count());
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        [$order, $payment] = $this->makePayment();
        $orderNumber = (string) $order['order_number'];

        try {
            $this->payments()->handleCallback('wechat', 'notify-bad-amount', $payment->out_trade_no, 'wx-bad', '29.99', true);
            $this->fail('金额不一致的回调应被拒绝');
        } catch (PaymentAmountMismatchException $e) {
            $this->assertStringContainsString('30.00', $e->getMessage());
            $this->assertStringContainsString('29.99', $e->getMessage());
        }

        $payment->refresh();
        $this->assertSame(PaymentStatus::CREATED, $payment->status);
        $this->assertNull($payment->transaction_id);

        $row = $this->orderRow($orderNumber);
        $this->assertSame(PayStatus::PENDING->value, $row['pay_status']);
        $this->assertSame(OrderStatus::PENDING_PAYMENT->value, $row['order_status']);

        // 被拒绝的回调也要留痕，且标记为已验证、已处理（便于对账排查）。
        $callback = DB::table('payment_callbacks')->where('notify_id', 'notify-bad-amount')->first();
        $this->assertNotNull($callback);
        $this->assertSame(1, (int) $callback->verified);
        $this->assertNotNull($callback->processed_at);
    }

    public function test_unverified_callback_is_rejected_before_persisting_payment_result(): void
    {
        [$order, $payment] = $this->makePayment();
        $orderNumber = (string) $order['order_number'];

        try {
            $this->payments()->handleCallback('wechat', 'notify-unverified', $payment->out_trade_no, 'wx-unverified', '30.00', false);
            $this->fail('未验签的回调应被拒绝');
        } catch (UnverifiedCallbackException $e) {
            $this->assertStringContainsString('notify-unverified', $e->getMessage());
        }

        $payment->refresh();
        $this->assertSame(PaymentStatus::CREATED, $payment->status);

        $row = $this->orderRow($orderNumber);
        $this->assertSame(PayStatus::PENDING->value, $row['pay_status']);
        $this->assertSame(OrderStatus::PENDING_PAYMENT->value, $row['order_status']);

        $callback = DB::table('payment_callbacks')->where('notify_id', 'notify-unverified')->first();
        $this->assertNotNull($callback);
        $this->assertSame(0, (int) $callback->verified);
        $this->assertNull($callback->processed_at);
    }

    public function test_duplicate_transaction_id_is_rejected(): void
    {
        [, $firstPayment] = $this->makePayment(['order_number' => 'ORD-TXN-A'], 'idem-txn-a');
        [$secondOrder, $secondPayment] = $this->makePayment(['order_number' => 'ORD-TXN-B'], 'idem-txn-b');

        $service = $this->payments();
        $service->handleCallback('wechat', 'notify-txn-a', $firstPayment->out_trade_no, 'wx-txn-same', '30.00', true);

        $this->expectException(DuplicateTransactionException::class);
        try {
            $service->handleCallback('wechat', 'notify-txn-b', $secondPayment->out_trade_no, 'wx-txn-same', '30.00', true);
        } finally {
            $secondPayment->refresh();
            $this->assertSame(PaymentStatus::CREATED, $secondPayment->status);
            $this->assertSame(PayStatus::PENDING->value, $this->orderRow((string) $secondOrder['order_number'])['pay_status']);
        }
    }

    public function test_close_expired_closes_stale_payments_and_rejects_late_callback(): void
    {
        [, $payment] = $this->makePayment();

        $closed = $this->payments()->closeExpired(30, now()->addMinutes(31));
        $this->assertSame(1, $closed);

        $payment->refresh();
        $this->assertSame(PaymentStatus::CLOSED, $payment->status);

        // 已关闭的支付单不再接受成功回调。
        $this->assertSame(0, $this->payments()->closeExpired(30, now()->addMinutes(62)));

        $this->expectException(PaymentNotPayableException::class);
        $this->payments()->handleCallback('wechat', 'notify-closed', $payment->out_trade_no, 'wx-closed', '30.00', true);
    }

    public function test_mark_paying_moves_created_payment_to_paying(): void
    {
        [, $payment] = $this->makePayment();

        $paying = $this->payments()->markPaying($payment->out_trade_no);
        $this->assertSame(PaymentStatus::PAYING, $paying->status);
    }
}
