<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Models;

use App\Domain\Fulfillment\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * 预约单：booked → arrived → converted；booked → cancelled / no_show。
 * order_number 只在转单（converted）时写入一次。
 *
 * @property int $id
 * @property int|null $member_id
 * @property int $store_id
 * @property int $slot_id
 * @property int $party_size
 * @property ReservationStatus $status
 * @property string|null $order_number
 * @property string|null $note
 * @property Carbon|null $arrived_at
 * @property Carbon|null $converted_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $no_show_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Reservation extends Model
{
    protected $table = 'reservations';

    protected $fillable = [
        'member_id',
        'store_id',
        'slot_id',
        'party_size',
        'status',
        'order_number',
        'note',
        'arrived_at',
        'converted_at',
        'cancelled_at',
        'no_show_at',
    ];

    protected $casts = [
        'member_id' => 'integer',
        'store_id' => 'integer',
        'slot_id' => 'integer',
        'party_size' => 'integer',
        'status' => ReservationStatus::class,
        'arrived_at' => 'datetime',
        'converted_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'no_show_at' => 'datetime',
    ];

    public function slot(): BelongsTo
    {
        return $this->belongsTo(ReservationSlot::class, 'slot_id');
    }
}
