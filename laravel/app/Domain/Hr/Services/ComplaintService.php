<?php

declare(strict_types=1);

namespace App\Domain\Hr\Services;

use App\Domain\Hr\Enums\ComplaintStatus;
use App\Domain\Hr\Exceptions\ComplaintException;
use App\Domain\Hr\Models\Complaint;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 客诉服务：受理、处理完成、关闭。
 *
 * 状态流转：pending → processing → resolved → closed，不允许跳级或回退。
 */
class ComplaintService
{
    public function __construct(
        protected StaffService $staff
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createComplaint(array $attributes): Complaint
    {
        $storeId = $this->requirePositiveInt($attributes['store_id'] ?? null, 'store_id');
        $type = $this->requireString($attributes['type'] ?? null, '客诉类型', 32);
        $content = $this->requireString($attributes['content'] ?? null, '客诉内容', 2000);

        return Complaint::query()->create([
            'store_id' => $storeId,
            'order_number' => $this->optionalString($attributes['order_number'] ?? null, '订单号', 64),
            'type' => $type,
            'content' => $content,
            'status' => ComplaintStatus::PENDING,
            'handler_id' => null,
            'resolution' => null,
            'handled_at' => null,
        ]);
    }

    /**
     * 受理：pending → processing，并记录处理人。
     */
    public function accept(int $complaintId, int $handlerId): Complaint
    {
        $this->staff->assertActive($handlerId);

        return $this->transition($complaintId, ComplaintStatus::PENDING, ComplaintStatus::PROCESSING, [
            'handler_id' => $handlerId,
        ]);
    }

    /**
     * 处理完成：processing → resolved。
     */
    public function resolve(int $complaintId, ?string $resolution = null): Complaint
    {
        return $this->transition($complaintId, ComplaintStatus::PROCESSING, ComplaintStatus::RESOLVED, [
            'resolution' => $resolution === null
                ? null
                : $this->requireString($resolution, '处理结果', 1000),
            'handled_at' => Carbon::now(),
        ]);
    }

    /**
     * 关闭：resolved → closed。
     */
    public function close(int $complaintId): Complaint
    {
        return $this->transition($complaintId, ComplaintStatus::RESOLVED, ComplaintStatus::CLOSED);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function transition(int $complaintId, string $from, string $to, array $extra = []): Complaint
    {
        return DB::transaction(function () use ($complaintId, $from, $to, $extra): Complaint {
            $complaint = Complaint::query()->lockForUpdate()->findOrFail($complaintId);
            $current = (string) $complaint->status;

            if ($current !== $from) {
                throw ComplaintException::invalidTransition($complaintId, $current, $to);
            }

            $complaint->fill($extra + ['status' => $to])->save();

            return $complaint->refresh();
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
}
