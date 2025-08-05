<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Base
{
    use HasFactory;

    //table
    protected $table = 'coupon';

    protected $fillable = [
        'title', 'code', 'type', 'discount', 'limit', 'expire_at', 'status', 'data','days',
        'send_way','num','desc',
    ];

    protected $casts = [
        'data' => 'array',
    ];


    public function getCreatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    public function getUpdatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }


    public function user()
    {
        return $this->hasMany(CouponUser::class, 'coupon_id');
    }

    public function order()
    {
        return $this->hasMany(CouponOrder::class, 'coupon_id');
    }

    public function product()
    {
        return $this->hasMany(CouponProduct::class, 'coupon_id');
    }

    /**
     * 使用某个优惠券返回新的价格
     */
    public function getNewPrice(&$cart, &$cart_items)
    {
        $product = $this->product;
        $product_id_in = [];
        foreach ($product as $key => $value) {
            $product_id_in[] = $value->product_id;
        }
        $total_amount = $cart['checked_total_amount'];
        $coupon_id = 0;
        foreach ($cart_items as $key => $value) {
            $product_id = $value['product_id'];
            if (in_array($product_id, $product_id_in)) {
                $price = $value['price'];
                $num = $value['num'];
                $one_price = bcmul($price, $num, 2);
                $value['price'] =  $this->getRowPrice($one_price);
                $sub_amount = bcsub($price, $value['price'], 2);
                $total_amount = bcsub($total_amount, $sub_amount, 2);
                $coupon_id = request()->input('coupon_id', '');
                break;
            }
        }
        $cart['sub_amount'] =  '';
        if ($sub_amount > 0) {
            $cart['sub_amount'] = '-'.$sub_amount;
        }
        $cart['checked_total_amount'] = $total_amount;
        $cart['coupon_id'] = $coupon_id;
    }

    public function getRowPrice($price)
    {
        switch ($this->type) {
            case 'price':
                $price = $this->discount;
                break;
            case 'discount':
                $discount = $this->discount;
                $discount = bcdiv($discount, 10, 2);
                $price = bcmul($price, $discount, 2);
                break;
        }
        return $price;
    }


}
