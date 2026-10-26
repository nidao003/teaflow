<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Hr;

use App\Domain\Hr\Enums\StaffStatus;
use App\Domain\Hr\Enums\TaskPriority;
use App\Domain\Hr\Enums\TaskStatus;
use App\Domain\Hr\Exceptions\StaffNotSchedulableException;
use App\Domain\Hr\Exceptions\TaskException;
use App\Domain\Hr\Services\TaskService;

class TaskServiceTest extends HrTestCase
{
    private TaskService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(TaskService::class);
    }

    public function test_overdue_task_is_detected_and_listed(): void
    {
        $overdue = $this->service->createTask([
            'store_id' => self::STORE_ID,
            'title' => '盘点上月物料差异',
            'due_at' => now()->subDay(),
        ]);
        $this->service->createTask([
            'store_id' => self::STORE_ID,
            'title' => '明日新品陈列',
            'due_at' => now()->addDay(),
        ]);

        $this->assertTrue($this->service->isOverdue($overdue));

        $overdueTasks = $this->service->overdueTasks(self::STORE_ID);
        $this->assertCount(1, $overdueTasks);
        $this->assertSame('盘点上月物料差异', $overdueTasks->first()->title);
    }

    public function test_complete_task_is_not_overdue_anymore(): void
    {
        $task = $this->service->createTask([
            'store_id' => self::STORE_ID,
            'title' => '清理设备',
            'due_at' => now()->subDay(),
        ]);

        $completed = $this->service->complete((int) $task->getKey());

        $this->assertSame(TaskStatus::DONE, $completed->status);
        $this->assertNotNull($completed->completed_at);
        $this->assertFalse($this->service->isOverdue($completed));
        $this->assertCount(0, $this->service->overdueTasks(self::STORE_ID));
    }

    public function test_cancelled_task_is_not_overdue(): void
    {
        $task = $this->service->createTask([
            'store_id' => self::STORE_ID,
            'title' => '取消的任务',
            'due_at' => now()->subDay(),
        ]);

        $cancelled = $this->service->cancel((int) $task->getKey());

        $this->assertSame(TaskStatus::CANCELLED, $cancelled->status);
        $this->assertFalse($this->service->isOverdue($cancelled));
    }

    public function test_assign_requires_active_staff(): void
    {
        $active = $this->createStaff(['name' => '在职']);
        $disabled = $this->createStaff(['name' => '停用', 'status' => StaffStatus::DISABLED]);

        $task = $this->service->createTask(['store_id' => self::STORE_ID, 'title' => '指派测试']);
        $assigned = $this->service->assign((int) $task->getKey(), (int) $active->getKey());
        $this->assertSame($active->getKey(), $assigned->assignee_id);

        try {
            $this->service->assign((int) $task->getKey(), (int) $disabled->getKey());
            $this->fail('停用员工不可被指派');
        } catch (StaffNotSchedulableException) {
            $this->assertSame($active->getKey(), $task->refresh()->assignee_id);
        }
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $task = $this->service->createTask(['store_id' => self::STORE_ID, 'title' => '状态流转']);
        $this->service->cancel((int) $task->getKey());

        $this->expectException(TaskException::class);
        $this->service->complete((int) $task->getKey());
    }

    public function test_priority_defaults_and_validation(): void
    {
        $task = $this->service->createTask(['store_id' => self::STORE_ID, 'title' => '默认优先级']);
        $this->assertSame(TaskPriority::NORMAL, $task->priority);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->createTask([
            'store_id' => self::STORE_ID,
            'title' => '非法优先级',
            'priority' => 'blocker',
        ]);
    }

    public function test_overdue_tasks_are_scoped_by_store(): void
    {
        $this->service->createTask([
            'store_id' => self::STORE_ID,
            'title' => '本店任务',
            'due_at' => now()->subDay(),
        ]);
        $this->service->createTask([
            'store_id' => 2,
            'title' => '他店任务',
            'due_at' => now()->subDay(),
        ]);

        $this->assertCount(1, $this->service->overdueTasks(self::STORE_ID));
        $this->assertCount(1, $this->service->overdueTasks(2));
    }
}
