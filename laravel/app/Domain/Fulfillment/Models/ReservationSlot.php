<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Models;

use App\Domain\Fulfillment\Enums\ReservationSlotStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * 预约时段：容量由 capacity 控制，booked_count 为已预约人数（防超卖的关键字段）。
 *
 * @property int $id
 * @property int $store_id
 * @property string $date 预约日期 Y-m-d
 * @property string $start_time HH:MM
 * @property string $end_time HH:MM
 * @property int $capacity
 * @property int $booked_count
 * @property ReservationSlotStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ReservationSlot extends Model
{
    protected $table = 'reservation_slots';

    protected $fillable = [
        'store_id',
        'date',
        'start_time',
        'end_time',
        'capacity',
        'booked_count',
        'status',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'capacity' => 'integer',
        'booked_count' => 'integer',
        'status' => ReservationSlotStatus::class,
    ];

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'slot_id');
    }

    public function remaining(): int
    {
        return max(0, $this->capacity - $this->booked_count);
    }

    public function isFull(): bool
    {
        return $this->booked_count >= $this->capacity;
    }

    public function isActive(): bool
    {
        return $this->status === ReservationSlotStatus::ACTIVE;
    }
}
