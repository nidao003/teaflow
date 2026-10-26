<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Fulfillment;

use App\Domain\Fulfillment\Enums\PickupStatus;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Exceptions\InvalidStateTransitionException;

class PickupServiceTest extends FulfillmentTestCase
{
    public function test_create_pickup_starts_as_pending_with_auto_pickup_no(): void
    {
        $pickup = $this->makePickup();

        $this->assertSame(PickupStatus::PENDING, $pickup->status);
        $this->assertSame('ORDER-P-1', $pickup->order_number);
        $this->assertSame(1, $pickup->store_id);
        $this->assertNotEmpty($pickup->pickup_no);
        $this->assertStringStartsWith('P', $pickup->pickup_no);
        $this->assertNull($pickup->ready_at);
        $this->assertNull($pickup->picked_at);
    }

    public function test_create_rejects_duplicate_order_number(): void
    {
        $this->makePickup(['order_number' => 'ORDER-DUP']);

        $this->expectException(FulfillmentException::class);
        $this->makePickup(['order_number' => 'ORDER-DUP']);
    }

    public function test_status_flow_pending_ready_picked(): void
    {
        $pickup = $this->makePickup();

        $ready = $this->pickups()->markReady($pickup);
        $this->assertSame(PickupStatus::READY, $ready->status);
        $this->assertNotNull($ready->ready_at);
        $this->assertNull($ready->picked_at);

        $picked = $this->pickups()->markPicked($ready);
        $this->assertSame(PickupStatus::PICKED, $picked->status);
        $this->assertNotNull($picked->picked_at);

        // 已取餐不可回退到可取餐
        $this->expectException(InvalidStateTransitionException::class);
        $this->pickups()->markReady($picked->fresh());
    }

    public function test_mark_picked_requires_ready(): void
    {
        $pickup = $this->makePickup();

        try {
            $this->pickups()->markPicked($pickup);
            $this->fail('未制作完成的单不应允许取餐');
        } catch (InvalidStateTransitionException) {
            // expected
        }

        $this->assertSame(PickupStatus::PENDING, $pickup->fresh()->status);
        $this->assertNull($pickup->fresh()->picked_at);
    }

    public function test_mark_ready_is_idempotent(): void
    {
        $pickup = $this->makePickup();
        $ready = $this->pickups()->markReady($pickup);
        $firstReadyAt = $ready->ready_at;

        $again = $this->pickups()->markReady($ready);

        $this->assertSame(PickupStatus::READY, $again->status);
        $this->assertSame($firstReadyAt?->toDateTimeString(), $again->ready_at?->toDateTimeString());
    }

    public function test_cancel_pending_and_ready_pickups(): void
    {
        $pending = $this->makePickup(['order_number' => 'ORDER-C1']);
        $cancelled = $this->pickups()->cancel($pending);
        $this->assertSame(PickupStatus::CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);

        $ready = $this->pickups()->markReady($this->makePickup(['order_number' => 'ORDER-C2']));
        $this->assertSame(PickupStatus::CANCELLED, $this->pickups()->cancel($ready)->status);

        // 再次取消是非法流转
        $this->expectException(InvalidStateTransitionException::class);
        $this->pickups()->cancel($cancelled->fresh());
    }

    public function test_picked_pickup_cannot_be_cancelled(): void
    {
        $pickup = $this->pickups()->markPicked(
            $this->pickups()->markReady($this->makePickup())
        );

        $this->expectException(InvalidStateTransitionException::class);
        $this->pickups()->cancel($pickup);
    }

    public function test_create_requires_order_number_and_store(): void
    {
        $this->expectException(FulfillmentException::class);
        $this->pickups()->create(['store_id' => 1]);
    }
}
