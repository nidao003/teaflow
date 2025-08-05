<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $table = 'cart';

    protected $fillable = [
        'device_id',
        'store_id',
        'user_id',
        'total_quantity',
        'total_mix_amount',
        'total_amount',
        'tag',
        'checked',
        'subtotal',
    ];

    /**
     * 追加字段
     */
    public $appends = [
        'checked_total_quantity',
        'checked_total_amount',
        'checked_total_mix_amount',
        'is_checked_all',
    ];

    /**
     * 取is_checked_all
     */
    public function getIsCheckedAllAttribute()
    {
        if ($this->items()->where('checked', 1)->count() == $this->items()->count()) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * 取checked_total_quantity
     */
    public function getCheckedTotalQuantityAttribute()
    {
        return CartItem::where('cart_id', $this->id)->where('checked', 1)->sum('num');
    }
    /**
     * 取checked_total_amount
     */
    public function getCheckedTotalAmountAttribute()
    {
        return CartItem::where('cart_id', $this->id)->where('checked', 1)->sum('subtotal') ;
    }
    /**
     * 取checked_total_mix_amount
     */
    public function getCheckedTotalMixAmountAttribute()
    {
        return CartItem::where('cart_id', $this->id)->where('checked', 1)->sum('mix_amount') ;
    }

    public function items()
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }


}
