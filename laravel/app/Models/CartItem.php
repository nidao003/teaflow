<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;

    protected $table = 'cart_item';

    public $timestamps = false;

    protected $fillable = [
        'cart_id',
        'product_id',
        'num',
        'price',
        'subtotal',
        'spec',
        'attr',
        'mix',
        'mix_amount',
        'total_price',
        'mix_price',
    ];

    public $appends = [
        'str',
    ];

    /**
     * 追加字段 str，由 spec attr mix组成
     */
    public function getStrAttribute()
    {
        return self::getStr($this->spec, $this->attr, $this->mix);
    }

    public static function getStr($spec, $attr, $mix)
    {
        $str = $spec . '|' . $attr . '|' . $mix;
        if (substr($str, -1) == '|') {
            $str = substr($str, 0, -1);
        }
        $str = str_replace('|', ' ', $str);
        $str = "（".$str."）";
        return $str;
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

}
