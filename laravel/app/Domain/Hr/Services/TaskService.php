<?php

declare(strict_types=1);

namespace App\Domain\Hr\Services;

use App\Domain\Hr\Enums\TaskPriority;
use App\Domain\Hr\Enums\TaskStatus;
use App\Domain\Hr\Exceptions\TaskException;
use App\Domain\Hr\Models\Task;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 门店任务服务：指派、开始、完成、取消、逾期判断。
 *
 * 状态流转：pending → in_progress → done；pending/in_progress → cancelled。
 */
class TaskService
{
    public function __construct(
        protected StaffService $staff
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTask(array $attributes): Task
    {
        $storeId = $this->requirePositiveInt($attributes['store_id'] ?? null, 'store_id');
        $title = $this->requireString($attributes['title'] ?? null, '任务标题', 128);

        $assigneeId = $attributes['assignee_id'] ?? null;
        if ($assigneeId !== null) {
            $assigneeId = $this->requirePositiveInt($assigneeId, 'assignee_id');
            $this->staff->assertActive($assigneeId);
        }

        $priority = $attributes['priority'] ?? TaskPriority::NORMAL;
        if (! is_string($priority)) {
            throw new InvalidArgumentException('非法任务优先级');
        }
        TaskPriority::assertValid($priority);

        return Task::query()->create([
            'store_id' => $storeId,
            'assignee_id' => $assigneeId,
            'title' => $title,
            'description' => $this->optionalString($attributes['description'] ?? null, '任务描述', 1000),
            'due_at' => $this->optionalDateTime($attributes['due_at'] ?? null),
            'status' => TaskStatus::PENDING,
            'priority' => $priority,
            'completed_at' => null,
        ]);
    }

    public function assign(int $taskId, int $staffId): Task
    {
        $this->staff->assertActive($staffId);

        return $this->transition($taskId, [TaskStatus::PENDING, TaskStatus::IN_PROGRESS], null, [
            'assignee_id' => $staffId,
        ]);
    }

    public function start(int $taskId): Task
    {
        return $this->transition($taskId, [TaskStatus::PENDING], TaskStatus::IN_PROGRESS);
    }

    public function complete(int $taskId, ?DateTimeInterface $now = null): Task
    {
        return $this->transition(
            $taskId,
            [TaskStatus::PENDING, TaskStatus::IN_PROGRESS],
            TaskStatus::DONE,
            ['completed_at' => $now === null ? Carbon::now() : Carbon::instance($now)]
        );
    }

    public function cancel(int $taskId): Task
    {
        return $this->transition($taskId, [TaskStatus::PENDING, TaskStatus::IN_PROGRESS], TaskStatus::CANCELLED);
    }

    /**
     * 逾期：有截止时间、状态未结束、且截止时间早于当前时间。
     */
    public function isOverdue(Task $task, ?DateTimeInterface $now = null): bool
    {
        if ($task->due_at === null) {
            return false;
        }
        if (! in_array((string) $task->status, TaskStatus::openStatuses(), true)) {
            return false;
        }

        $now = $now === null ? Carbon::now() : Carbon::instance($now);

        return $task->due_at->lt($now);
    }

    /**
     * @return Collection<int, Task>
     */
    public function overdueTasks(int $storeId, ?DateTimeInterface $now = null): Collection
    {
        $now = $now === null ? Carbon::now() : Carbon::instance($now);

        return Task::query()
            ->where('store_id', $storeId)
            ->whereIn('status', TaskStatus::openStatuses())
            ->whereNotNull('due_at')
            ->where('due_at', '<', $now)
            ->orderBy('due_at')
            ->get();
    }

    /**
     * @return Collection<int, Task>
     */
    public function listForStore(int $storeId, ?string $status = null): Collection
    {
        return Task::query()
            ->where('store_id', $storeId)
            ->when($status !== null, function ($query) use ($status): void {
                TaskStatus::assertValid($status);
                $query->where('status', $status);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<string>  $allowedFrom
     * @param  array<string, mixed>  $extra
     */
    protected function transition(int $taskId, array $allowedFrom, ?string $to, array $extra = []): Task
    {
        return DB::transaction(function () use ($taskId, $allowedFrom, $to, $extra): Task {
            $task = Task::query()->lockForUpdate()->findOrFail($taskId);
            $from = (string) $task->status;

            if (! in_array($from, $allowedFrom, true)) {
                throw TaskException::invalidTransition($taskId, $from, $to ?? $from);
            }

            if ($to !== null) {
                $extra['status'] = $to;
            }

            $task->fill($extra)->save();

            return $task->refresh();
        });
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

    protected function requireString(mixed $value, string $label, int $maxLength): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException($label.'不能为空');
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException($label.'不能为空');
        }
        if (mb_strlen($trimmed) > $maxLength) {
            throw new InvalidArgumentException($label.'长度不能超过 '.$maxLength.' 个字符');
        }

        return $trimmed;
    }

    protected function optionalString(mixed $value, string $label, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->requireString($value, $label, $maxLength);
    }

    protected function optionalDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }
        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                throw new InvalidArgumentException('截止时间格式非法：'.$value);
            }
        }

        throw new InvalidArgumentException('截止时间格式非法');
    }
}
