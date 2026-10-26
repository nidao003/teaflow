<?php

declare(strict_types=1);

namespace App\Domain\Hr\Services;

use App\Domain\Hr\Enums\StaffStatus;
use App\Domain\Hr\Exceptions\StaffNotSchedulableException;
use App\Domain\Hr\Models\Staff;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * 员工服务：建档、更新、停用/启用、可排班校验。
 *
 * 注意：staff 与登录账号 users 解耦，user_id 可空；门店存在性只做逻辑校验，
 * 不跨域依赖 App\Models\Store。
 */
class StaffService
{
    public const DEFAULT_ROLE = 'staff';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createStaff(array $attributes): Staff
    {
        $data = $this->normalizeCreate($attributes);

        return Staff::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateStaff(int $staffId, array $attributes): Staff
    {
        $staff = $this->find($staffId);
        $staff->fill($this->normalizeUpdate($attributes))->save();

        return $staff->refresh();
    }

    public function find(int $staffId): Staff
    {
        return Staff::query()->findOrFail($staffId);
    }

    public function disable(int $staffId): Staff
    {
        $staff = $this->find($staffId);
        $staff->fill(['status' => StaffStatus::DISABLED])->save();

        return $staff->refresh();
    }

    public function enable(int $staffId): Staff
    {
        $staff = $this->find($staffId);
        $staff->fill(['status' => StaffStatus::ACTIVE])->save();

        return $staff->refresh();
    }

    /**
     * 断言员工在职，否则抛 StaffNotSchedulableException。
     */
    public function assertActive(int $staffId): Staff
    {
        $staff = $this->find($staffId);

        if (! $staff->isActive()) {
            throw StaffNotSchedulableException::disabled($staffId);
        }

        return $staff;
    }

    /**
     * 断言员工可在指定门店排班（在职 + 门店一致）。
     */
    public function assertSchedulable(int $staffId, int $storeId): Staff
    {
        $staff = $this->assertActive($staffId);

        if ((int) $staff->store_id !== $storeId) {
            throw StaffNotSchedulableException::storeMismatch($staffId, (int) $staff->store_id, $storeId);
        }

        return $staff;
    }

    /**
     * @return Collection<int, Staff>
     */
    public function listByStore(int $storeId, ?int $status = null): Collection
    {
        return Staff::query()
            ->where('store_id', $storeId)
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalizeCreate(array $attributes): array
    {
        $storeId = $this->requirePositiveInt($attributes['store_id'] ?? null, 'store_id');
        $name = $this->requireString($attributes['name'] ?? null, '员工姓名', 64);
        $role = array_key_exists('role', $attributes)
            ? $this->requireString($attributes['role'], '员工角色', 32)
            : self::DEFAULT_ROLE;

        $status = $attributes['status'] ?? StaffStatus::ACTIVE;
        if (! is_int($status) && ! is_string($status) && ! is_bool($status)) {
            throw new InvalidArgumentException('非法员工状态');
        }
        $status = (int) $status;
        StaffStatus::assertValid($status);

        return [
            'store_id' => $storeId,
            'user_id' => $this->optionalPositiveInt($attributes['user_id'] ?? null, 'user_id'),
            'name' => $name,
            'role' => $role,
            'phone' => $this->optionalString($attributes['phone'] ?? null, '手机号', 32),
            'status' => $status,
            'hired_at' => $this->optionalDateTime($attributes['hired_at'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function normalizeUpdate(array $attributes): array
    {
        $data = [];

        if (array_key_exists('store_id', $attributes)) {
            $data['store_id'] = $this->requirePositiveInt($attributes['store_id'], 'store_id');
        }
        if (array_key_exists('user_id', $attributes)) {
            $data['user_id'] = $this->optionalPositiveInt($attributes['user_id'], 'user_id');
        }
        if (array_key_exists('name', $attributes)) {
            $data['name'] = $this->requireString($attributes['name'], '员工姓名', 64);
        }
        if (array_key_exists('role', $attributes)) {
            $data['role'] = $this->requireString($attributes['role'], '员工角色', 32);
        }
        if (array_key_exists('phone', $attributes)) {
            $data['phone'] = $this->optionalString($attributes['phone'], '手机号', 32);
        }
        if (array_key_exists('status', $attributes)) {
            $status = (int) $attributes['status'];
            StaffStatus::assertValid($status);
            $data['status'] = $status;
        }
        if (array_key_exists('hired_at', $attributes)) {
            $data['hired_at'] = $this->optionalDateTime($attributes['hired_at']);
        }

        return $data;
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

    protected function optionalPositiveInt(mixed $value, string $field): ?int
    {
        return $value === null ? null : $this->requirePositiveInt($value, $field);
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
                throw new InvalidArgumentException('时间格式非法：'.$value);
            }
        }

        throw new InvalidArgumentException('时间格式非法');
    }
}
