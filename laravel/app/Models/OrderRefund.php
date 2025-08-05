<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderRefund extends Model
{
    use HasFactory;

    protected $table = 'order_refund';

    protected $fillable = [
        'order_id',
        'order_number',
        'refund_number',
        'refund_number_platform',
        'refund_amount',
        'refund_status',
        'refunded_at',
        'refund_reason',
    ];

    // 定义日期字段，使其自动转换为Carbon实例
    protected $dates = [
        'created_at',
        'updated_at',
        'refunded_at',
    ];

    // 日期格式化
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'refunded_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
