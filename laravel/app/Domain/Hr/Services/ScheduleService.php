<?php

declare(strict_types=1);

namespace App\Domain\Hr\Services;

use App\Domain\Hr\Enums\ScheduleStatus;
use App\Domain\Hr\Enums\ShiftType;
use App\Domain\Hr\Exceptions\ScheduleConflictException;
use App\Domain\Hr\Models\Schedule;
use App\Domain\Hr\Support\Dates;
use App\Domain\Hr\Support\Time;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * 排班服务。
 *
 * 冲突规则：
 * 1. 同一员工同一时间只允许一个班次（按分钟区间比较，跨天班次顺延 +24h）；
 * 2. unique(staff_id, date, shift_type) 兜底同一天同班次重复排班；
 * 3. 已取消（cancelled）的排班不参与冲突；
 * 4. 停用员工不可排班，排班门店必须与员工归属门店一致。
 */
class ScheduleService
{
    public function __construct(
        protected StaffService $staff
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createSchedule(array $attributes): Schedule
    {
        $data = $this->normalize($attributes);

        $this->staff->assertSchedulable($data['staff_id'], $data['store_id']);
        $this->assertNoConflict($data['staff_id'], $data['date'], $data['start_time'], $data['end_time']);

        try {
            return Schedule::query()->create($data);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw ScheduleConflictException::duplicateShift(
                    $data['staff_id'],
                    $data['date'],
                    $data['shift_type']
                );
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateSchedule(int $scheduleId, array $attributes): Schedule
    {
        $schedule = Schedule::query()->findOrFail($scheduleId);
        $merged = $this->normalize($this->mergeAttributes($schedule, $attributes), $scheduleId);

        $this->staff->assertSchedulable($merged['staff_id'], $merged['store_id']);

        if ($merged['status'] !== ScheduleStatus::CANCELLED) {
            $this->assertNoConflict(
                $merged['staff_id'],
                $merged['date'],
                $merged['start_time'],
                $merged['end_time'],
                $scheduleId
            );
        }

        try {
            $schedule->fill($merged)->save();
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw ScheduleConflictException::duplicateShift(
                    $merged['staff_id'],
                    $merged['date'],
                    $merged['shift_type']
                );
            }

            throw $e;
        }

        return $schedule->refresh();
    }

    public function cancel(int $scheduleId): Schedule
    {
        $schedule = Schedule::query()->findOrFail($scheduleId);

        if ($schedule->status === ScheduleStatus::CANCELLED) {
            return $schedule;
        }
        if ($schedule->status === ScheduleStatus::FINISHED) {
            throw new InvalidArgumentException('已完成的排班不可取消');
        }

        $schedule->fill(['status' => ScheduleStatus::CANCELLED])->save();

        return $schedule->refresh();
    }

    /**
     * 查询某员工在指定日期与时间段内冲突的排班（跨天班次会连带检查前后一天）。
     *
     * @return Collection<int, Schedule>
     */
    public function conflicts(
        int $staffId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeScheduleId = null
    ): Collection {
        $date = Dates::normalize($date);
        $start = Time::normalize($startTime);
        $end = Time::normalize($endTime);
        $target = Time::interval($date, $start, $end);

        return Schedule::query()
            ->where('staff_id', $staffId)
            ->whereIn('date', [
                Dates::shift($date, -1),
                $date,
                Dates::shift($date, 1),
            ])
            ->where('status', '!=', ScheduleStatus::CANCELLED)
            ->when($excludeScheduleId !== null, fn ($query) => $query->whereKeyNot($excludeScheduleId))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->filter(function (Schedule $schedule) use ($target): bool {
                $existing = Time::interval(
                    (string) $schedule->date,
                    (string) $schedule->start_time,
                    (string) $schedule->end_time
                );

                return Time::overlaps($target, $existing);
            })
            ->values();
    }

    /**
     * @return Collection<int, Schedule>
     */
    public function listForStaff(int $staffId, ?string $from = null, ?string $to = null): Collection
    {
        return Schedule::query()
            ->where('staff_id', $staffId)
            ->when($from !== null, fn ($query) => $query->where('date', '>=', Dates::normalize($from)))
            ->when($to !== null, fn ($query) => $query->where('date', '<=', Dates::normalize($to)))
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
    }

    protected function assertNoConflict(
        int $staffId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeScheduleId = null
    ): void {
        $conflict = $this->conflicts($staffId, $date, $startTime, $endTime, $excludeScheduleId)->first();

        if ($conflict !== null) {
            throw ScheduleConflictException::overlap($date, $startTime, $endTime, (int) $conflict->getKey());
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalize(array $attributes, ?int $scheduleId = null): array
    {
        $storeId = $this->requirePositiveInt($attributes['store_id'] ?? null, 'store_id');
        $staffId = $this->requirePositiveInt($attributes['staff_id'] ?? null, 'staff_id');

        $dateRaw = $attributes['date'] ?? null;
        if (! is_string($dateRaw)) {
            throw new InvalidArgumentException('排班日期不能为空');
        }
        $date = Dates::normalize($dateRaw);

        $shiftType = $attributes['shift_type'] ?? null;
        if (! is_string($shiftType)) {
            throw new InvalidArgumentException('班次类型不能为空');
        }
        ShiftType::assertValid($shiftType);

        $startRaw = $attributes['start_time'] ?? null;
        $endRaw = $attributes['end_time'] ?? null;
        if (! is_string($startRaw) || ! is_string($endRaw)) {
            throw new InvalidArgumentException('班次开始/结束时间不能为空');
        }
        $start = Time::normalize($startRaw);
        $end = Time::normalize($endRaw);
        if ($start === $end) {
            throw ScheduleConflictException::invalidRange($start, $end);
        }

        $status = $attributes['status'] ?? ScheduleStatus::SCHEDULED;
        if (! is_string($status)) {
            throw new InvalidArgumentException('非法排班状态');
        }
        ScheduleStatus::assertValid($status);

        $data = [
            'store_id' => $storeId,
            'staff_id' => $staffId,
            'date' => $date,
            'shift_type' => $shiftType,
            'start_time' => $start,
            'end_time' => $end,
            'status' => $status,
        ];

        if ($scheduleId !== null && in_array($status, [ScheduleStatus::CANCELLED, ScheduleStatus::FINISHED], true)) {
            // 状态变更不改变班次内容，仍按完整字段写入。
            $data['status'] = $status;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function mergeAttributes(Schedule $schedule, array $attributes): array
    {
        $current = [
            'store_id' => (int) $schedule->store_id,
            'staff_id' => (int) $schedule->staff_id,
            'date' => (string) $schedule->date,
            'shift_type' => (string) $schedule->shift_type,
            'start_time' => (string) $schedule->start_time,
            'end_time' => (string) $schedule->end_time,
            'status' => (string) $schedule->status,
        ];

        return array_replace($current, $attributes);
    }

    protected function requirePositiveInt(mixed $value, string $field): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException($field.' 必须为正整数');
        }

        $int = (int) $value;
        if ($int <= 0) {
            throw new InvalidArgumentException($field.' 必须为正整数');
        }

        return $int;
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000'
            || $driverCode === 1062
            || $driverCode === 19
            || str_contains($e->getMessage(), 'UNIQUE constraint failed')
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
