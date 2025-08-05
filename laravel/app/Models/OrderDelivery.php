<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderDelivery extends Model
{
    use HasFactory;

    protected $table = 'order_delivery';

    protected $fillable = [
        'order_id',
        'delivery_fee',
        'dispatched_at',
        'delivered_at',
        'deliveryman_name',
        'deliveryman_phone',
        'delivery_platform',
    ];
}
