<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductAttrValue extends Model
{
    use HasFactory;
    protected $table = 'product_attr_value';

    public $timestamps = false;

    protected $fillable = [
        'attr_id',
        'value',
        'sort',
    ];


    public function productAttr()
    {
        return $this->belongsTo(ProductAttr::class, 'attr_id');
    }
    /**
     * 保存 attr_id 值时，先判断ProductAttr是否存在,如果不存在要报错
     */
    public function setAttrIdAttribute($value)
    {
        $attr = ProductAttr::find($value);
        if (!$attr) {
            throw new \Exception('属性不存在');
        }
        $this->attributes['attr_id'] = $value;
    }
    /**
     * 保存数据的时候，sort没值的时候为0
     */
    protected function setSortAttribute($value)
    {
        $this->attributes['sort'] = $value ?? 0;
    }


}
