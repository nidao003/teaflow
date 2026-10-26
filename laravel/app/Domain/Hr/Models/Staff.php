<?php

declare(strict_types=1);

namespace App\Domain\Hr\Models;

use App\Domain\Hr\Enums\StaffStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 员工（门店员工档案，与登录账号 users 解耦，user_id 可空）。
 *
 * @property int $id
 * @property int $store_id
 * @property int|null $user_id
 * @property string $name
 * @property string $role
 * @property string|null $phone
 * @property int $status
 * @property Carbon|null $hired_at
 */
class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'store_id',
        'user_id',
        'name',
        'role',
        'phone',
        'status',
        'hired_at',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'user_id' => 'integer',
        'status' => 'integer',
        'hired_at' => 'datetime',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'staff_id', 'id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'staff_id', 'id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id', 'id');
    }

    public function complaints(): HasMany
    {
        return $this->hasMany(Complaint::class, 'handler_id', 'id');
    }

    public function isActive(): bool
    {
        return (int) $this->status === StaffStatus::ACTIVE;
    }
}
