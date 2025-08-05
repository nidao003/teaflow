<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponOrder extends Model
{
    use HasFactory;

    protected $table = 'coupon_order';

    protected $fillable = [
        'order_id',
        'coupon_id',
        'user_id',
        'status',
        'price',
    ];

    public function getCreatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function coupon()
    {
        return $this->belongsTo(CouponUser::class, 'coupon_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
