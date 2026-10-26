<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Fulfillment;

use App\Domain\Fulfillment\Enums\ReservationSlotStatus;
use App\Domain\Fulfillment\Enums\ReservationStatus;
use App\Domain\Fulfillment\Exceptions\CapacityExceededException;
use App\Domain\Fulfillment\Exceptions\ConcurrentCapacityUpdateException;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Exceptions\InvalidStateTransitionException;
use App\Domain\Fulfillment\Models\Reservation;
use App\Domain\Fulfillment\Models\ReservationSlot;
use App\Domain\Fulfillment\Services\ReservationService;
use Illuminate\Support\Facades\DB;

class ReservationServiceTest extends FulfillmentTestCase
{
    public function test_create_slot_persists_fields(): void
    {
        $slot = $this->makeSlot(['capacity' => 8]);

        $this->assertSame(1, $slot->store_id);
        $this->assertSame('2026-11-01', $slot->date);
        $this->assertSame('18:00', $slot->start_time);
        $this->assertSame('19:00', $slot->end_time);
        $this->assertSame(8, $slot->capacity);
        $this->assertSame(0, $slot->booked_count);
        $this->assertSame(ReservationSlotStatus::ACTIVE, $slot->status);
        $this->assertSame(8, $slot->remaining());
    }

    public function test_create_slot_rejects_duplicate_store_date_and_start(): void
    {
        $this->makeSlot();

        $this->expectException(FulfillmentException::class);
        $this->makeSlot();
    }

    public function test_create_slot_allows_other_stores_and_starts(): void
    {
        $this->makeSlot();
        $this->makeSlot(['store_id' => 2]);
        $this->makeSlot(['start_time' => '19:00', 'end_time' => '20:00']);

        $this->assertSame(3, ReservationSlot::query()->count());
    }

    public function test_create_slot_validates_time_window_and_capacity(): void
    {
        try {
            $this->makeSlot(['start_time' => '19:00', 'end_time' => '18:00']);
            $this->fail('结束时间早于开始时间未被拒绝');
        } catch (FulfillmentException) {
            // expected
        }

        $this->expectException(FulfillmentException::class);
        $this->makeSlot(['capacity' => 0]);
    }

    public function test_book_increments_booked_count(): void
    {
        $slot = $this->makeSlot(['capacity' => 10]);

        $reservation = $this->reservations()->book([
            'slot_id' => $slot->id,
            'party_size' => 3,
            'member_id' => 88,
            'note' => '靠窗',
        ]);

        $this->assertSame(ReservationStatus::BOOKED, $reservation->status);
        $this->assertSame($slot->id, $reservation->slot_id);
        $this->assertSame(1, $reservation->store_id);
        $this->assertSame(3, $reservation->party_size);
        $this->assertSame(88, $reservation->member_id);
        $this->assertNull($reservation->order_number);
        $this->assertSame(3, $slot->fresh()->booked_count);
        $this->assertSame(7, $slot->fresh()->remaining());
    }

    public function test_capacity_is_enforced(): void
    {
        $slot = $this->makeSlot(['capacity' => 3]);
        $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 2]);

        try {
            $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 2]);
            $this->fail('容量不足未被拒绝');
        } catch (CapacityExceededException $exception) {
            $this->assertStringContainsString('容量不足', $exception->getMessage());
        }

        $this->assertSame(2, $slot->fresh()->booked_count);
        $this->assertSame(1, Reservation::query()->count());

        // 剩余 1 个名额仍可预约
        $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 1]);
        $this->assertSame(3, $slot->fresh()->booked_count);
        $this->assertTrue($slot->fresh()->isFull());
    }

    public function test_party_size_larger_than_capacity_is_rejected(): void
    {
        $slot = $this->makeSlot(['capacity' => 2]);

        $this->expectException(CapacityExceededException::class);
        $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 3]);
    }

    public function test_book_rejects_invalid_party_size_and_unknown_slot(): void
    {
        $slot = $this->makeSlot();

        try {
            $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 0]);
            $this->fail('0 人预约未被拒绝');
        } catch (FulfillmentException) {
            // expected
        }

        $this->expectException(FulfillmentException::class);
        $this->reservations()->book(['slot_id' => 999999, 'party_size' => 1]);
    }

    public function test_closed_slot_rejects_book(): void
    {
        $slot = $this->makeSlot();
        DB::table('reservation_slots')->where('id', $slot->id)->update([
            'status' => ReservationSlotStatus::CLOSED->value,
        ]);

        $this->expectException(FulfillmentException::class);
        $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 1]);
    }

    public function test_arrive_then_convert_writes_order_number_only_once(): void
    {
        $slot = $this->makeSlot();
        $reservation = $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 2]);

        $arrived = $this->reservations()->arrive($reservation);
        $this->assertSame(ReservationStatus::ARRIVED, $arrived->status);
        $this->assertNotNull($arrived->arrived_at);

        $converted = $this->reservations()->convert($arrived, 'ORDER-1001');
        $this->assertSame(ReservationStatus::CONVERTED, $converted->status);
        $this->assertSame('ORDER-1001', $converted->order_number);
        $this->assertNotNull($converted->converted_at);

        // 二次转单拒绝，且不覆盖第一次写入的 order_number
        try {
            $this->reservations()->convert($converted, 'ORDER-2002');
            $this->fail('二次转单未被拒绝');
        } catch (InvalidStateTransitionException) {
            // expected
        }

        $this->assertSame('ORDER-1001', $converted->fresh()->order_number);
    }

    public function test_convert_requires_arrived_status(): void
    {
        $slot = $this->makeSlot();
        $reservation = $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 1]);

        $this->expectException(InvalidStateTransitionException::class);
        $this->reservations()->convert($reservation, 'ORDER-X');
    }

    public function test_convert_rejects_empty_order_number(): void
    {
        $slot = $this->makeSlot();
        $reservation = $this->reservations()->arrive(
            $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 1])
        );

        $this->expectException(FulfillmentException::class);
        $this->reservations()->convert($reservation, '   ');
    }

    public function test_cancel_releases_capacity(): void
    {
        $slot = $this->makeSlot(['capacity' => 4]);
        $reservation = $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 3]);
        $this->assertSame(3, $slot->fresh()->booked_count);

        $cancelled = $this->reservations()->cancel($reservation);
        $this->assertSame(ReservationStatus::CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame(0, $slot->fresh()->booked_count);

        // 取消后名额释放，其他顾客可预约
        $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 4]);
        $this->assertSame(4, $slot->fresh()->booked_count);

        $this->expectException(InvalidStateTransitionException::class);
        $this->reservations()->cancel($cancelled->fresh());
    }

    public function test_no_show_releases_capacity(): void
    {
        $slot = $this->makeSlot(['capacity' => 2]);
        $reservation = $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 2]);

        $noShow = $this->reservations()->noShow($reservation);
        $this->assertSame(ReservationStatus::NO_SHOW, $noShow->status);
        $this->assertNotNull($noShow->no_show_at);
        $this->assertSame(0, $slot->fresh()->booked_count);

        // 爽约后不能再到店
        $this->expectException(InvalidStateTransitionException::class);
        $this->reservations()->arrive($noShow->fresh());
    }

    public function test_arrived_reservation_cannot_be_cancelled_or_no_show(): void
    {
        $slot = $this->makeSlot();
        $reservation = $this->reservations()->arrive(
            $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 2])
        );

        try {
            $this->reservations()->cancel($reservation);
            $this->fail('到店后仍允许取消');
        } catch (InvalidStateTransitionException) {
            // expected
        }

        $this->expectException(InvalidStateTransitionException::class);
        $this->reservations()->noShow($reservation);
    }

    public function test_concurrent_committed_booking_cannot_oversell(): void
    {
        $slot = $this->makeSlot(['capacity' => 2]);
        $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 1]);

        // 模拟另一个请求已提交预约：容量被占满
        DB::table('reservation_slots')->where('id', $slot->id)->update(['booked_count' => 2]);

        try {
            $this->reservations()->book(['slot_id' => $slot->id, 'party_size' => 1]);
            $this->fail('并发场景出现超卖');
        } catch (CapacityExceededException) {
            // expected
        }

        $this->assertSame(2, $slot->fresh()->booked_count);
        $this->assertSame(1, Reservation::query()->count());
    }

    public function test_capacity_cas_conflict_is_retried_then_throws(): void
    {
        $slot = $this->makeSlot(['capacity' => 10]);

        /** @var ReservationService $conflictingService */
        $conflictingService = new class extends ReservationService
        {
            /** 每次写容量前模拟被其他事务抢先提交。 */
            protected function persistBookedCount(ReservationSlot $slot, int $expected, int $newCount): bool
            {
                ReservationSlot::query()->whereKey($slot->id)->update([
                    'booked_count' => $expected + 1,
                    'updated_at' => now(),
                ]);

                return parent::persistBookedCount($slot, $expected, $newCount);
            }
        };

        $thrown = null;

        try {
            $conflictingService->book(['slot_id' => $slot->id, 'party_size' => 2]);
        } catch (ConcurrentCapacityUpdateException $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(ConcurrentCapacityUpdateException::class, $thrown);
        // 重试全部失败后整体回滚：容量与预约单都不变
        $this->assertSame(0, $slot->fresh()->booked_count);
        $this->assertSame(0, Reservation::query()->count());
    }
}
