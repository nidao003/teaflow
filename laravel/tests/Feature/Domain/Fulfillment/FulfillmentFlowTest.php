<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Fulfillment;

use App\Domain\Fulfillment\Enums\PickupStatus;
use App\Domain\Fulfillment\Enums\ReservationStatus;
use App\Domain\Fulfillment\Enums\VerifyCodeStatus;
use App\Domain\Fulfillment\Exceptions\InvalidStateTransitionException;
use App\Domain\Fulfillment\Exceptions\VerifyCodeUsedException;

/**
 * 核销码 + 自提 + 预约的端到端串联（各域内服务组合使用）。
 */
class FulfillmentFlowTest extends FulfillmentTestCase
{
    public function test_pickup_flow_with_one_time_verify_code(): void
    {
        $pickup = $this->makePickup(['order_number' => 'ORDER-FLOW-1']);
        $code = $this->issueCode(['order_number' => 'ORDER-FLOW-1']);

        $pickup = $this->pickups()->markReady($pickup);
        $this->assertSame(PickupStatus::READY, $pickup->status);

        $used = $this->verifyCodes()->use($code->code, 1, 9, 'ORDER-FLOW-1');
        $this->assertSame(VerifyCodeStatus::USED, $used->status);

        $pickup = $this->pickups()->markPicked($pickup);
        $this->assertSame(PickupStatus::PICKED, $pickup->status);
        $this->assertNotNull($pickup->picked_at);

        // 一次性：同一码不能再核销
        $this->expectException(VerifyCodeUsedException::class);
        $this->verifyCodes()->use($code->code, 1);
    }

    public function test_reservation_flow_book_arrive_convert_keeps_capacity(): void
    {
        $slot = $this->makeSlot(['capacity' => 2]);

        $reservation = $this->reservations()->book([
            'slot_id' => $slot->id,
            'party_size' => 2,
            'member_id' => 66,
        ]);
        $this->assertSame(2, $slot->fresh()->booked_count);

        $reservation = $this->reservations()->arrive($reservation);
        $reservation = $this->reservations()->convert($reservation, 'ORDER-RES-001');

        $this->assertSame(ReservationStatus::CONVERTED, $reservation->status);
        $this->assertSame('ORDER-RES-001', $reservation->order_number);
        // 已转单占用容量，不释放
        $this->assertSame(2, $slot->fresh()->booked_count);

        $this->expectException(InvalidStateTransitionException::class);
        $this->reservations()->cancel($reservation);
    }

    public function test_no_show_frees_capacity_for_new_guest(): void
    {
        $slot = $this->makeSlot(['capacity' => 1]);

        $first = $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 1]);
        $this->reservations()->noShow($first);
        $this->assertSame(0, $slot->fresh()->booked_count);

        $second = $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 1]);

        $this->assertSame(ReservationStatus::BOOKED, $second->status);
        $this->assertSame(1, $slot->fresh()->booked_count);
        $this->assertSame(2, $slot->fresh()->reservations()->count());
    }
}
