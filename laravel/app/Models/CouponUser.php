<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponUser extends Model
{
    use HasFactory;

    protected $table = 'coupon_user';

    protected $fillable = [
        'user_id',
        'coupon_id',
        'status',
        'expire_at',
        'order_number',
        'get_way',
    ];

    //追加字段
    protected $appends = [
        'expire_color',
        'status_color',
        'status_label',
        'product',
        'support_goods',
    ];

    public function getSupportGoodsAttribute()
    {
        if (!$this->product) {
            return;
        }
        $str = '';
        foreach ($this->product as $k => $v) {
            $str .= $v->title. ',';
        }
        return $str;
    }

    public function getProductAttribute()
    {
        return $this->coupon->product;
    }

    public function getExpireColorAttribute()
    {
        $date = date('Y-m-d', strtotime($this->expire_at));
        $now = date('Y-m-d');
        if ($date < $now) {
            return 'red';
        }
        return 'green';
    }

    public function getStatusColorAttribute()
    {
        switch ($this->status) {
            case 'wait':
                return 'blue';
                break;
            case 'used':
                return 'green';
                break;
            case 'expired':
                return 'red';
                break;
        }
    }

    public function getStatusLabelAttribute()
    {
        switch ($this->status) {
            case 'wait':
                return '待使用';
                break;
            case 'used':
                return '已使用';
                break;
            case 'expired':
                return '已过期';
                break;
        }
    }

    //created_at时间格式
    public function getCreatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    public function getExpireAtAttribute($value)
    {
        return date('Y-m-d', strtotime($value));
    }

    /**
     * 添加is_expired字段
     */
    public function getIsExpiredAttribute()
    {
        if ($this->expire_at > date('Y-m-d')) {
            return true;
        }
        return false;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
