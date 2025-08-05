<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;
    protected $expire_time = 10;
    protected $table = 'orders';

    protected $fillable = [
        'order_number',
        'get_code',
        'seller_id',
        'store_id',
        'user_id',
        'delivery_type',
        'order_status',
        'pay_status',
        'delivery_status',
        'payment_method',
        'delivery_method',
        'paid_at',
        'total_quantity',
        'total_mix_amount',
        'total_amount',
        'paid_amount',
        'refunded_amount',
        'waiting_at',
        'get_time',
        'finished_at',
        'note',
    ];
    /**
     * 追加属性
     */
    protected $appends = [
        'order_status_type',
        'order_status_label',
        'delivery_method_label',
        'delivery_method_type',
        'items',
        'can_pay_time',
        'can_pay',
        'show_code',
    ];

    public function getShowCodeAttribute()
    {
        if (!in_array($this->order_status, ['pending','expired'])) {
            $date = date('Ymd', strtotime($this->created_at));
            if ($date == date('Ymd')) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * can_pay
     */
    public function getCanPayAttribute()
    {
        return $this->order_status == 'pending' && time() - strtotime($this->created_at) <= $this->expire_time * 60;
    }

    /**
     * can_pay_time
     */
    public function getCanPayTimeAttribute()
    {
        if ($this->order_status != 'pending') {
            return '';
        }
        $created_at = strtotime($this->created_at);
        $max_time = $this->expire_time * 60;
        $less =  $max_time + $created_at - time();
        if ($less > 0) {
            return $less;
        } else {
            Order::where('id', $this->id)->update(['order_status' => 'expired']);
            //过期订单退回优惠券
            \App\Classes\Coupon::refund($this->id);
            return '';
        }
    }
    /**
     * 时间格式化 created_at
     *
     */
    public function getCreatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    public function getUpdatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    public function getItemsAttribute()
    {
        return $this->orderItems()->get();
    }

    public function getDeliveryMethodTypeAttribute()
    {
        if ($this->delivery_method == 'self-pickup') {
            return 'success';
        }
        return 'warning';
    }

    public function getDeliveryMethodLabelAttribute()
    {
        if ($this->delivery_method == 'self-pickup') {
            return '自提';
        }
        return '外送';
    }

    public function getOrderStatusTypeAttribute()
    {
        switch ($this->order_status) {
            case 'pending':
                return 'warning';
            case 'paid':
                return 'success';
            case 'refunded':
                return 'error';
            case 'expired':
                return 'error';
            case 'success':
                return 'success';
            case 'wait_get':
                return 'warning';
        }
    }

    public function getOrderStatusLabelAttribute()
    {
        switch ($this->order_status) {
            case 'pending':
                return '待付款';
            case 'paid':
                return '制作中';
            case 'wait_get':
                return '待取餐';
            case 'refunded':
                return '已退款';
            case 'expired':
                return '已取消';
            case'success':
                return'已完成';
        }
    }

    //关联商家
    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id');
    }
    //关联店铺
    public function store()
    {
        return $this->belongsTo(Store::class, 'store_id');
    }
    //关联用户
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function orderDelivery()
    {
        return $this->hasOne(OrderDelivery::class, 'order_id');
    }

    public function orderPay()
    {
        return $this->hasMany(OrderPay::class, 'order_id');
    }

    public function coupon()
    {
        return $this->hasOne(CouponOrder::class, 'order_id');
    }
    
    /**
     * 获取可退款金额
     * 可退款金额 = 支付金额 - 已退款金额
     */
    public function getRealAmountAttribute()
    {
        return floatval($this->paid_amount) - floatval($this->refunded_amount);
    }

}
