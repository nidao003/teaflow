<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pay extends Model
{
    use HasFactory;

    // 表名

    protected $table = 'pay';

    // 可以被批量赋值的属性。

    protected $fillable = [
        'device_id',
        'store_id',
        'order_number',
        'pay_number',
        'pay_method',
        'total_amount',
        'paid_amount',
        'pay_status',
        'paid_at',
        'refund_number',
        'refund_number_platform',
        'refunded_amount',
        'refunded_at',
    ];

    // 关联订单
    public function order()
    {
        return $this->belongsTo(Orders::class, 'order_number', 'order_number');
    }

    // 关联退款
    public function refund()
    {
        return $this->belongsTo(OrderRefund::class, 'refund_number', 'refund_number');
    }


}
