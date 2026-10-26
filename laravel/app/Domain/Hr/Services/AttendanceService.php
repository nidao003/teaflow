<?php

declare(strict_types=1);

namespace App\Domain\Hr\Services;

use App\Domain\Hr\Enums\AttendanceSource;
use App\Domain\Hr\Enums\AttendanceStatus;
use App\Domain\Hr\Enums\ScheduleStatus;
use App\Domain\Hr\Exceptions\AttendanceException;
use App\Domain\Hr\Models\Attendance;
use App\Domain\Hr\Models\Schedule;
use App\Domain\Hr\Support\Dates;
use App\Domain\Hr\Support\Time;
use Illuminate\Database\Eloquent\Collection;

/**
 * 考勤服务：签到/签退、迟到/早退判定、缺勤补录、异常清单。
 *
 * 时间统一存 HH:MM；判定按分钟比较，跨天班次（结束 <= 开始）顺延 +24h。
 */
class AttendanceService
{
    /** 迟到/早退宽限分钟数。 */
    public const GRACE_MINUTES = 10;

    public function __construct(
        protected StaffService $staff
    ) {}

    public function clockIn(
        int $staffId,
        string $date,
        string $time,
        string $source = AttendanceSource::MANUAL
    ): Attendance {
        $date = Dates::normalize($date);
        $time = Time::normalize($time);
        AttendanceSource::assertValid($source);

        $staff = $this->staff->find($staffId);
        if (! $staff->isActive()) {
            throw AttendanceException::staffDisabled($staffId);
        }

        $existing = $this->findByStaffDate($staffId, $date);
        if ($existing !== null && $existing->clock_in !== null) {
            throw AttendanceException::alreadyClockedIn($staffId, $date);
        }

        $status = $this->statusForClockIn($staffId, $date, $time);

        if ($existing !== null) {
            $existing->fill([
                'clock_in' => $time,
                'source' => $source,
                'status' => $status,
            ])->save();

            return $existing->refresh();
        }

        return Attendance::query()->create([
            'staff_id' => $staffId,
            'date' => $date,
            'clock_in' => $time,
            'clock_out' => null,
            'status' => $status,
            'source' => $source,
        ]);
    }

    public function clockOut(int $staffId, string $date, string $time): Attendance
    {
        $date = Dates::normalize($date);
        $time = Time::normalize($time);

        $record = $this->findByStaffDate($staffId, $date);
        if ($record === null || $record->clock_in === null) {
            throw AttendanceException::notClockedIn($staffId, $date);
        }
        if ($record->clock_out !== null) {
            throw AttendanceException::alreadyClockedOut($staffId, $date);
        }

        $clockIn = (string) $record->clock_in;
        $status = $this->statusForClockOut($staffId, $date, $clockIn, $time, (string) $record->status);

        $record->fill([
            'clock_out' => $time,
            'status' => $status,
        ])->save();

        return $record->refresh();
    }

    /**
     * 为指定门店、日期有排班但未打卡的员工补录缺勤记录，返回补录条数。
     */
    public function markAbsent(int $storeId, string $date): int
    {
        $date = Dates::normalize($date);

        $staffIds = Schedule::query()
            ->where('store_id', $storeId)
            ->where('date', $date)
            ->where('status', '!=', ScheduleStatus::CANCELLED)
            ->distinct()
            ->pluck('staff_id');

        $created = 0;
        foreach ($staffIds as $staffId) {
            $staffId = (int) $staffId;
            if ($this->findByStaffDate($staffId, $date) !== null) {
                continue;
            }

            Attendance::query()->create([
                'staff_id' => $staffId,
                'date' => $date,
                'clock_in' => null,
                'clock_out' => null,
                'status' => AttendanceStatus::ABSENT,
                'source' => AttendanceSource::SYSTEM,
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * 异常考勤（迟到/早退/缺勤）。
     *
     * @return Collection<int, Attendance>
     */
    public function exceptions(int $storeId, string $date): Collection
    {
        $date = Dates::normalize($date);

        return Attendance::query()
            ->where('date', $date)
            ->whereIn('status', AttendanceStatus::exceptionStatuses())
            ->whereHas('staff', fn ($query) => $query->where('store_id', $storeId))
            ->orderBy('staff_id')
            ->get();
    }

    public function findByStaffDate(int $staffId, string $date): ?Attendance
    {
        return Attendance::query()
            ->where('staff_id', $staffId)
            ->where('date', $date)
            ->first();
    }

    protected function statusForClockIn(int $staffId, string $date, string $clockIn): string
    {
        $schedule = $this->scheduledShift($staffId, $date);
        if ($schedule === null) {
            return AttendanceStatus::PRESENT;
        }

        $start = Time::toMinutes((string) $schedule->start_time);
        $actual = Time::toMinutes($clockIn);

        return $actual > $start + self::GRACE_MINUTES
            ? AttendanceStatus::LATE
            : AttendanceStatus::PRESENT;
    }

    protected function statusForClockOut(
        int $staffId,
        string $date,
        string $clockIn,
        string $clockOut,
        string $currentStatus
    ): string {
        if ($currentStatus === AttendanceStatus::LATE) {
            return AttendanceStatus::LATE;
        }

        $schedule = $this->scheduledShift($staffId, $date);
        if ($schedule === null) {
            return $currentStatus;
        }

        $start = Time::toMinutes((string) $schedule->start_time);
        $end = Time::toMinutes((string) $schedule->end_time);
        $clockInMinutes = Time::toMinutes($clockIn);
        $clockOutMinutes = Time::toMinutes($clockOut);

        // 非跨天班次：签退必须晚于签到，否则视为非法时间。
        if ($end > $start && $clockOutMinutes <= $clockInMinutes) {
            throw AttendanceException::invalidClockOut($clockIn, $clockOut);
        }
        if ($clockInMinutes === $clockOutMinutes) {
            throw AttendanceException::invalidClockOut($clockIn, $clockOut);
        }

        $expected = Time::duration((string) $schedule->start_time, (string) $schedule->end_time);
        $actual = Time::duration($clockIn, $clockOut);

        return $actual < $expected - self::GRACE_MINUTES
            ? AttendanceStatus::EARLY_LEAVE
            : $currentStatus;
    }

    protected function scheduledShift(int $staffId, string $date): ?Schedule
    {
        return Schedule::query()
            ->where('staff_id', $staffId)
            ->where('date', $date)
            ->where('status', '!=', ScheduleStatus::CANCELLED)
            ->orderBy('start_time')
            ->first();
    }
}
