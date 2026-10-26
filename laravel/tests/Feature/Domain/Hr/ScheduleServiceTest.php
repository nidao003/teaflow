<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Hr;

use App\Domain\Hr\Enums\ScheduleStatus;
use App\Domain\Hr\Enums\ShiftType;
use App\Domain\Hr\Enums\StaffStatus;
use App\Domain\Hr\Exceptions\ScheduleConflictException;
use App\Domain\Hr\Exceptions\StaffNotSchedulableException;
use App\Domain\Hr\Services\ScheduleService;

class ScheduleServiceTest extends HrTestCase
{
    public function test_overlapping_shifts_are_rejected(): void
    {
        $staff = $this->createStaff();
        $service = $this->app->make(ScheduleService::class);

        $service->createSchedule([
            'store_id' => self::STORE_ID,
            'staff_id' => (int) $staff->getKey(),
            'date' => $this->today(),
            'shift_type' => ShiftType::MORNING,
            'start_time' => '09:00',
            'end_time' => '14:00',
        ]);

        $this->expectException(ScheduleConflictException::class);
        $service->createSchedule([
            'store_id' => self::STORE_ID,
            'staff_id' => (int) $staff->getKey(),
            'date' => $this->today(),
            'shift_type' => ShiftType::AFTERNOON,
            'start_time' => '13:00',
            'end_time' => '18:00',
        ]);
    }

    public function test_same_shift_type_twice_is_rejected_even_without_time_overlap(): void
    {
        $staff = $this->createStaff();
        $service = $this->app->make(ScheduleService::class);

        $this->createSchedule($staff, $this->today(), '09:00', '12:00', ['shift_type' => ShiftType::MORNING]);

        $this->expectException(ScheduleConflictException::class);
        $service->createSchedule([
            'store_id' => self::STORE_ID,
            'staff_id' => (int) $staff->getKey(),
            'date' => $this->today(),
            'shift_type' => ShiftType::MORNING,
            'start_time' => '14:00',
            'end_time' => '18:00',
        ]);
    }

    public function test_adjacent_shifts_are_allowed(): void
    {
        $staff = $this->createStaff();
        $service = $this->app->make(ScheduleService::class);

        $service->createSchedule([
            'store_id' => self::STORE_ID,
            'staff_id' => (int) $staff->getKey(),
            'date' => $this->today(),
            'shift_type' => ShiftType::MORNING,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $service->createSchedule([
            'store_id' => self::STORE_ID,
            'staff_id' => (int) $staff->getKey(),
            'date' => $this->today(),
            'shift_type' => ShiftType::AFTERNOON,
            'start_time' => '12:00',
            'end_time' => '18:00',
        ]);

        $this->assertCount(2, $service->listForStaff((int) $staff->getKey()));
    }

    public function test_overnight_shift_conflicts_with_next_day_shift(): void
    {
        $staff = $this->createStaff();
        $service = $this->app->make(ScheduleService::class);

        $this->createSchedule($staff, $this->today(), '22:00', '02:00', ['shift_type' => ShiftType::EVENING]);

        $this->expectException(ScheduleConflictException::class);
        $service->createSchedule([
            'store_id' => self::STORE_ID,
            'staff_id' => (int) $staff->getKey(),
            'date' => $this->tomorrow(),
            'shift_type' => ShiftType::MORNING,
            'start_time' => '01:00',
            'end_time' => '06:00',
        ]);
    }

    public function test_cancelled_schedule_does_not_conflict(): void
    {
        $staff = $this->createStaff();
        $service = $this->app->make(ScheduleService::class);

        $cancelled = $this->createSchedule($staff, $this->today(), '09:00', '14:00', ['shift_type' => ShiftType::MORNING]);
        $service->cancel((int) $cancelled->getKey());

        $schedule = $service->createSchedule([
            'store_id' => self::STORE_ID,
            'staff_id' => (int) $staff->getKey(),
            'date' => $this->today(),
            'shift_type' => ShiftType::AFTERNOON,
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);

        $this->assertSame(ScheduleStatus::SCHEDULED, $schedule->status);
        $this->assertSame(ShiftType::AFTERNOON, $schedule->shift_type);
    }

    public function test_disabled_staff_cannot_be_scheduled(): void
    {
        $staff = $this->createStaff(['status' => StaffStatus::DISABLED]);

        $this->expectException(StaffNotSchedulableException::class);
        $this->app->make(ScheduleService::class)->createSchedule([
            'store_id' => self::STORE_ID,
            'staff_id' => (int) $staff->getKey(),
            'date' => $this->today(),
            'shift_type' => ShiftType::MORNING,
            'start_time' => '09:00',
            'end_time' => '14:00',
        ]);
    }

    public function test_schedule_store_must_match_staff_store(): void
    {
        $staff = $this->createStaff();

        $this->expectException(StaffNotSchedulableException::class);
        $this->app->make(ScheduleService::class)->createSchedule([
            'store_id' => 2,
            'staff_id' => (int) $staff->getKey(),
            'date' => $this->today(),
            'shift_type' => ShiftType::MORNING,
            'start_time' => '09:00',
            'end_time' => '14:00',
        ]);
    }
}
