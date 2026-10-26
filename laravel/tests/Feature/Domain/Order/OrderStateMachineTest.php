<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Order;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Exceptions\IllegalOrderTransitionException;
use App\Domain\Order\Exceptions\OrderNotFoundException;
use App\Domain\Order\Services\OrderStateMachine;
use Illuminate\Support\Facades\DB;

class OrderStateMachineTest extends OrderTestCase
{
    private function stateMachine(): OrderStateMachine
    {
        return app(OrderStateMachine::class);
    }

    public function test_legal_chain_updates_order_status_and_records_events(): void
    {
        $order = $this->makeOrder();
        $orderNumber = (string) $order['order_number'];

        $chain = [
            OrderStatus::PAID,
            OrderStatus::ACCEPTED,
            OrderStatus::MAKING,
            OrderStatus::READY,
            OrderStatus::COMPLETED,
        ];

        foreach ($chain as $to) {
            $this->stateMachine()->transition($orderNumber, $to, 'order_'.$to->value);
            $this->assertSame($to->value, $this->orderRow($orderNumber)['order_status']);
        }

        $events = DB::table('order_events')->where('order_number', $orderNumber)->orderBy('id')->get();
        $this->assertCount(5, $events);
        $this->assertSame(OrderStatus::PENDING_PAYMENT->value, $events->first()->from_status);
        $this->assertSame(OrderStatus::COMPLETED->value, $events->last()->to_status);
        $this->assertSame('order_completed', $events->last()->event);
    }

    public function test_pending_payment_can_be_cancelled(): void
    {
        $order = $this->makeOrder();
        $orderNumber = (string) $order['order_number'];

        $this->stateMachine()->transition($orderNumber, OrderStatus::CANCELLED, 'order_cancelled');

        $this->assertSame(OrderStatus::CANCELLED->value, $this->orderRow($orderNumber)['order_status']);
    }

    public function test_illegal_transition_is_rejected_and_logged(): void
    {
        $order = $this->makeOrder();
        $orderNumber = (string) $order['order_number'];

        try {
            $this->stateMachine()->transition($orderNumber, OrderStatus::COMPLETED, 'skip_paid');
            $this->fail('非法状态迁移应抛出异常');
        } catch (IllegalOrderTransitionException $e) {
            $this->assertStringContainsString($orderNumber, $e->getMessage());
            $this->assertStringContainsString('pending_payment', $e->getMessage());
            $this->assertStringContainsString('completed', $e->getMessage());
        }

        // 非法迁移不改变订单状态，但必须留痕（异常在事务提交后抛出）。
        $this->assertSame(OrderStatus::PENDING_PAYMENT->value, $this->orderRow($orderNumber)['order_status']);

        $event = DB::table('order_events')
            ->where('order_number', $orderNumber)
            ->where('event', 'illegal_transition')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame(OrderStatus::PENDING_PAYMENT->value, $event->from_status);
        $this->assertSame(OrderStatus::COMPLETED->value, $event->to_status);
    }

    public function test_refund_cycle_allows_multiple_partial_refunds(): void
    {
        $order = $this->makePaidOrder();
        $orderNumber = (string) $order['order_number'];
        $machine = $this->stateMachine();

        $machine->transition($orderNumber, OrderStatus::REFUNDING, 'refund_requested');
        $machine->transition($orderNumber, OrderStatus::PARTIALLY_REFUNDED, 'refund_succeeded');
        $machine->transition($orderNumber, OrderStatus::REFUNDING, 'refund_requested');
        $machine->transition($orderNumber, OrderStatus::REFUNDED, 'refund_succeeded');

        $this->assertSame(OrderStatus::REFUNDED->value, $this->orderRow($orderNumber)['order_status']);
        $this->assertTrue(OrderStatus::REFUNDED->isFinal());
    }

    public function test_can_transit_and_assert_can_transit(): void
    {
        $machine = $this->stateMachine();

        $this->assertTrue($machine->canTransit(OrderStatus::PENDING_PAYMENT, OrderStatus::PAID));
        $this->assertFalse($machine->canTransit(OrderStatus::COMPLETED, OrderStatus::PAID));
        $this->assertSame([], $machine->allowedTransitions(OrderStatus::CANCELLED));

        $this->expectException(IllegalOrderTransitionException::class);
        $machine->assertCanTransit(OrderStatus::PENDING_PAYMENT, OrderStatus::COMPLETED);
    }

    public function test_unknown_order_throws(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->stateMachine()->transition('ORDER-NOT-FOUND', OrderStatus::PAID, 'pay');
    }
}
