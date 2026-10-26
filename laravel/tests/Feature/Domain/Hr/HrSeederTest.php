<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Hr;

use App\Domain\Hr\Models\Attendance;
use App\Domain\Hr\Models\Complaint;
use App\Domain\Hr\Models\Schedule;
use App\Domain\Hr\Models\Staff;
use App\Domain\Hr\Models\Target;
use App\Domain\Hr\Models\Task;
use Database\Seeders\HrSeeder;

class HrSeederTest extends HrTestCase
{
    public function test_seeder_is_idempotent(): void
    {
        $this->seed(HrSeeder::class);
        $this->seed(HrSeeder::class);

        $this->assertSame(3, Staff::query()->where('store_id', HrSeeder::STORE_ID)->count());
        $this->assertSame(3, Schedule::query()->where('store_id', HrSeeder::STORE_ID)->count());
        $this->assertSame(2, Target::query()->where('store_id', HrSeeder::STORE_ID)->count());
        $this->assertSame(2, Task::query()->where('store_id', HrSeeder::STORE_ID)->count());
        $this->assertSame(2, Complaint::query()->where('store_id', HrSeeder::STORE_ID)->count());
        $this->assertSame(1, Attendance::query()->count());
    }

    public function test_seeder_creates_expected_business_states(): void
    {
        $this->seed(HrSeeder::class);

        $this->assertSame(1, Task::query()->where('status', 'pending')->count());
        $this->assertSame(1, Task::query()->where('status', 'in_progress')->count());
        $this->assertSame(1, Complaint::query()->where('status', 'pending')->count());
        $this->assertSame(1, Complaint::query()->where('status', 'processing')->count());
    }
}
