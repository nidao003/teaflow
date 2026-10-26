<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Hr\Enums\AttendanceStatus;
use App\Domain\Hr\Enums\ComplaintStatus;
use App\Domain\Hr\Enums\ScheduleStatus;
use App\Domain\Hr\Enums\ShiftType;
use App\Domain\Hr\Enums\StaffStatus;
use App\Domain\Hr\Enums\TaskPriority;
use App\Domain\Hr\Enums\TaskStatus;
use App\Domain\Hr\Models\Attendance;
use App\Domain\Hr\Models\Complaint;
use App\Domain\Hr\Models\Schedule;
use App\Domain\Hr\Models\Staff;
use App\Domain\Hr\Models\Target;
use App\Domain\Hr\Models\Task;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 门店运营/HR 域演示数据：员工、排班、目标、任务、客诉。
 *
 * 幂等：按业务唯一键 updateOrCreate，重复执行不会产生重复数据。
 */
class HrSeeder extends Seeder
{
    public const STORE_ID = 1;

    public function run(): void
    {
        DB::transaction(function (): void {
            $today = Carbon::now()->toDateString();
            $tomorrow = Carbon::now()->addDay()->toDateString();
            $period = Carbon::now()->format('Y-m');

            $staffRows = [
                ['name' => '王店长', 'role' => 'manager', 'phone' => '13800001001'],
                ['name' => '李收银', 'role' => 'cashier', 'phone' => '13800001002'],
                ['name' => '张小茶', 'role' => 'barista', 'phone' => '13800001003'],
            ];

            /** @var array<string, Staff> $staff */
            $staff = [];
            foreach ($staffRows as $row) {
                $staff[$row['name']] = Staff::query()->updateOrCreate(
                    ['store_id' => self::STORE_ID, 'name' => $row['name']],
                    $row + [
                        'status' => StaffStatus::ACTIVE,
                        'hired_at' => Carbon::now()->subMonths(6),
                    ]
                );
            }

            $scheduleRows = [
                ['staff' => '王店长', 'date' => $today, 'shift_type' => ShiftType::FULL, 'start_time' => '09:00', 'end_time' => '18:00'],
                ['staff' => '李收银', 'date' => $today, 'shift_type' => ShiftType::MORNING, 'start_time' => '09:00', 'end_time' => '14:00'],
                ['staff' => '张小茶', 'date' => $tomorrow, 'shift_type' => ShiftType::AFTERNOON, 'start_time' => '14:00', 'end_time' => '22:00'],
            ];

            foreach ($scheduleRows as $row) {
                Schedule::query()->updateOrCreate(
                    [
                        'staff_id' => (int) $staff[$row['staff']]->getKey(),
                        'date' => $row['date'],
                        'shift_type' => $row['shift_type'],
                    ],
                    [
                        'store_id' => self::STORE_ID,
                        'start_time' => $row['start_time'],
                        'end_time' => $row['end_time'],
                        'status' => ScheduleStatus::SCHEDULED,
                    ]
                );
            }

            Target::query()->updateOrCreate(
                ['store_id' => self::STORE_ID, 'period' => $period, 'metric' => 'revenue'],
                ['target_value' => '120000.00', 'actual_value' => '86000.00']
            );
            Target::query()->updateOrCreate(
                ['store_id' => self::STORE_ID, 'period' => $period, 'metric' => 'orders'],
                ['target_value' => '3000.00', 'actual_value' => '2100.00']
            );

            Task::query()->updateOrCreate(
                ['store_id' => self::STORE_ID, 'title' => '完成秋季新品陈列检查'],
                [
                    'assignee_id' => (int) $staff['张小茶']->getKey(),
                    'description' => '按陈列标准逐项拍照留档。',
                    'due_at' => Carbon::now()->addDays(2),
                    'status' => TaskStatus::PENDING,
                    'priority' => TaskPriority::HIGH,
                    'completed_at' => null,
                ]
            );
            Task::query()->updateOrCreate(
                ['store_id' => self::STORE_ID, 'title' => '盘点上月物料差异'],
                [
                    'assignee_id' => (int) $staff['李收银']->getKey(),
                    'description' => '核对损耗与库存差异，输出差异说明。',
                    'due_at' => Carbon::now()->subDay(),
                    'status' => TaskStatus::IN_PROGRESS,
                    'priority' => TaskPriority::NORMAL,
                    'completed_at' => null,
                ]
            );

            Complaint::query()->updateOrCreate(
                ['store_id' => self::STORE_ID, 'type' => 'service', 'content' => '高峰期等待时间过长。'],
                [
                    'order_number' => 'TF202610260001',
                    'status' => ComplaintStatus::PENDING,
                    'handler_id' => null,
                    'resolution' => null,
                    'handled_at' => null,
                ]
            );
            Complaint::query()->updateOrCreate(
                ['store_id' => self::STORE_ID, 'type' => 'product', 'content' => '饮品温度与要求不符。'],
                [
                    'order_number' => 'TF202610260002',
                    'status' => ComplaintStatus::PROCESSING,
                    'handler_id' => (int) $staff['王店长']->getKey(),
                    'resolution' => null,
                    'handled_at' => null,
                ]
            );

            // 今日已有排班的员工不补考勤，仅演示一条到岗记录（幂等）。
            Attendance::query()->updateOrCreate(
                [
                    'staff_id' => (int) $staff['王店长']->getKey(),
                    'date' => $today,
                ],
                [
                    'clock_in' => '09:00',
                    'clock_out' => null,
                    'status' => AttendanceStatus::PRESENT,
                    'source' => 'manual',
                ]
            );
        });
    }
}
