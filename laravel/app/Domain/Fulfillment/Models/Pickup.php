<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Models;

use App\Domain\Fulfillment\Enums\PickupStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * 自提单：一单一条（order_number 唯一），状态机 pending → ready → picked。
 *
 * @property int $id
 * @property string $order_number
 * @property string $pickup_no
 * @property int $store_id
 * @property PickupStatus $status
 * @property Carbon|null $ready_at
 * @property Carbon|null $picked_at
 * @property Carbon|null $cancelled_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Pickup extends Model
{
    protected $table = 'pickups';

    protected $fillable = [
        'order_number',
        'pickup_no',
        'store_id',
        'status',
        'ready_at',
        'picked_at',
        'cancelled_at',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'status' => PickupStatus::class,
        'ready_at' => 'datetime',
        'picked_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}
