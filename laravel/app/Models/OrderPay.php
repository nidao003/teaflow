<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderPay extends Model
{
    use HasFactory;

    protected $table = 'order_pay';

    protected $fillable = [
        'order_id',
        'type',
        'title',
        'desc',
        'amount',
    ];

    /**
     * 关联订单
     */
    public function order()
    {
        return $this->belongsTo(Orders::class, 'order_id');
    }
}
