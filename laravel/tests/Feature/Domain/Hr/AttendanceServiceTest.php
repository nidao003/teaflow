<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Hr;

use App\Domain\Hr\Enums\AttendanceStatus;
use App\Domain\Hr\Enums\ShiftType;
use App\Domain\Hr\Enums\StaffStatus;
use App\Domain\Hr\Exceptions\AttendanceException;
use App\Domain\Hr\Models\Attendance;
use App\Domain\Hr\Services\AttendanceService;

class AttendanceServiceTest extends HrTestCase
{
    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(AttendanceService::class);
    }

    public function test_clock_in_and_clock_out_marks_present(): void
    {
        $staff = $this->createStaff();
        $this->createSchedule($staff, $this->today(), '09:00', '17:00', ['shift_type' => ShiftType::MORNING]);

        $clockIn = $this->service->clockIn((int) $staff->getKey(), $this->today(), '08:55');
        $this->assertSame('08:55', $clockIn->clock_in);
        $this->assertSame(AttendanceStatus::PRESENT, $clockIn->status);

        $clockOut = $this->service->clockOut((int) $staff->getKey(), $this->today(), '17:05');
        $this->assertSame('17:05', $clockOut->clock_out);
        $this->assertSame(AttendanceStatus::PRESENT, $clockOut->status);
    }

    public function test_duplicate_clock_in_is_rejected(): void
    {
        $staff = $this->createStaff();
        $this->service->clockIn((int) $staff->getKey(), $this->today(), '09:00');

        try {
            $this->service->clockIn((int) $staff->getKey(), $this->today(), '09:01');
            $this->fail('同一天重复签到应当被拒绝');
        } catch (AttendanceException $e) {
            $this->assertStringContainsString('已签到', $e->getMessage());
        }

        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_clock_out_without_clock_in_is_rejected(): void
    {
        $staff = $this->createStaff();

        $this->expectException(AttendanceException::class);
        $this->service->clockOut((int) $staff->getKey(), $this->today(), '18:00');
    }

    public function test_late_clock_in_is_flagged(): void
    {
        $staff = $this->createStaff();
        $this->createSchedule($staff, $this->today(), '09:00', '17:00', ['shift_type' => ShiftType::MORNING]);

        $attendance = $this->service->clockIn((int) $staff->getKey(), $this->today(), '09:20');

        $this->assertSame(AttendanceStatus::LATE, $attendance->status);
    }

    public function test_early_leave_is_flagged(): void
    {
        $staff = $this->createStaff();
        $this->createSchedule($staff, $this->today(), '09:00', '17:00', ['shift_type' => ShiftType::MORNING]);

        $this->service->clockIn((int) $staff->getKey(), $this->today(), '08:58');
        $attendance = $this->service->clockOut((int) $staff->getKey(), $this->today(), '16:00');

        $this->assertSame(AttendanceStatus::EARLY_LEAVE, $attendance->status);
    }

    public function test_mark_absent_creates_records_and_lists_exceptions(): void
    {
        $onDuty = $this->createStaff(['name' => '已到岗']);
        $missing = $this->createStaff(['name' => '缺勤']);

        $this->createSchedule($onDuty, $this->today(), '09:00', '17:00', ['shift_type' => ShiftType::MORNING]);
        $this->createSchedule($missing, $this->today(), '09:00', '17:00', ['shift_type' => ShiftType::MORNING]);

        $this->service->clockIn((int) $onDuty->getKey(), $this->today(), '08:55');

        $this->assertSame(1, $this->service->markAbsent(self::STORE_ID, $this->today()));
        $this->assertSame(2, Attendance::query()->count());

        $absent = Attendance::query()->where('staff_id', $missing->getKey())->firstOrFail();
        $this->assertSame(AttendanceStatus::ABSENT, $absent->status);

        $exceptions = $this->service->exceptions(self::STORE_ID, $this->today());
        $this->assertCount(1, $exceptions);
        $this->assertSame($missing->getKey(), $exceptions->first()->staff_id);

        // 重复补录不会重复创建
        $this->assertSame(0, $this->service->markAbsent(self::STORE_ID, $this->today()));
    }

    public function test_disabled_staff_cannot_clock_in(): void
    {
        $staff = $this->createStaff(['status' => StaffStatus::DISABLED]);

        $this->expectException(AttendanceException::class);
        $this->service->clockIn((int) $staff->getKey(), $this->today(), '09:00');
    }
}
