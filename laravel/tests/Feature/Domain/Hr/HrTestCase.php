<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Hr;

use App\Domain\Hr\Enums\StaffStatus;
use App\Domain\Hr\Models\Schedule;
use App\Domain\Hr\Models\Staff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 门店运营/HR 域测试基类：RefreshDatabase 重建 sqlite 内存库。
 */
abstract class HrTestCase extends TestCase
{
    use RefreshDatabase;

    public const STORE_ID = 1;

    protected function createStaff(array $attributes = []): Staff
    {
        return Staff::query()->create(array_merge([
            'store_id' => self::STORE_ID,
            'user_id' => null,
            'name' => '测试员工',
            'role' => 'barista',
            'phone' => '13800000000',
            'status' => StaffStatus::ACTIVE,
            'hired_at' => now()->subMonth(),
        ], $attributes));
    }

    protected function createSchedule(Staff $staff, string $date, string $start, string $end, array $attributes = []): Schedule
    {
        return Schedule::query()->create(array_merge([
            'store_id' => (int) $staff->store_id,
            'staff_id' => (int) $staff->getKey(),
            'date' => $date,
            'shift_type' => 'full',
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'scheduled',
        ], $attributes));
    }

    protected function today(): string
    {
        return now()->toDateString();
    }

    protected function tomorrow(): string
    {
        return now()->addDay()->toDateString();
    }

    protected function yesterday(): string
    {
        return now()->subDay()->toDateString();
    }
}
