<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CouponProduct extends Model
{
    use HasFactory;

    protected $table = 'coupon_product';

    protected $fillable = [
        'coupon_id',
        'product_id',
    ];

    //不需要时间
    public $timestamps = false;

    //追加字段
    protected $appends = [
        'title',
    ];

    public function getTitleAttribute()
    {
        return $this->product->title ?? '';
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }
}
