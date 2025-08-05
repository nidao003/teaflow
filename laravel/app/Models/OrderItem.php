<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'order_items';

    protected $casts = [
        'data' => 'object',
    ];

    protected $fillable = [
        'order_id',
        'product_id',
        'product_title',
        'product_image',
        'price',
        'quantity',
        'subtotal',
        'data',
        'spec',
        'attr',
        'mix',
        'mix_amount',
    ];

    protected $appends = [
        'attr_label',
        'mix_label',
    ];

    public function getAttrLabelAttribute()
    {
        $str = '';
        if ($this->attr) {
            $str .= str_replace("|", "、", $this->attr);
        }
        return $str;
    }

    public function getMixLabelAttribute()
    {
        $str = '';
        if ($this->mix) {
            $str .= str_replace("|", "、", $this->mix);
        }
        return $str;
    }

    public function getFullAttr1Attribute()
    {
        $str = '';
        if ($this->attr) {
            $str .= str_replace("|", "、", $this->attr);
        }
        if ($this->mix) {
            $str .= str_replace("|", "、", $this->mix);
        }
        return $str;
    }
}
