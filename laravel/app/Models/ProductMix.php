<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductMix extends Model
{
    use HasFactory;


    protected $table = 'product_mix';

    protected $fillable = [
        'title',
        'price',
        'sort',
    ];

    public static $labels = [
        'title' => '小料名称',
        'price' => '价格',
        'sort' => '排序',
    ];

    //不需要时间
    public $timestamps = false;

    /**
     * 保存数据的时候，sort没值的时候为0
     */
    protected function setSortAttribute($value)
    {
        $this->attributes['sort'] = $value ?? 0;
    }

}
