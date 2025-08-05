<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductAttr extends Model
{
    use HasFactory;

    protected $table = 'product_attr';

    protected $fillable = [
        'title',
        'sort',
    ];

    public static $labels = [
        'title' => '属性名称',
        'sort' => '排序',
    ];


    //不需要时间
    public $timestamps = false;

    public $appends = ['values'];

    public function getValuesAttribute()
    {
        $all = $this->productAttrValues;
        $list = [];
        if ($all) {
            foreach ($all as $value) {
                $list[] = [
                    'id' => $value->id,
                    'value' => $value->value
                ];
            }
        }
        return $list;
    }

    public function productAttrValues()
    {
        return $this->hasMany(ProductAttrValue::class, 'attr_id', 'id')->orderBy('sort', 'desc')->orderBy('id', 'asc');
    }
    /**
     * 保存数据的时候，sort没值的时候为0
     */
    protected function setSortAttribute($value)
    {
        $this->attributes['sort'] = $value ?? 0;
    }

    public function save(array $options = [])
    {
        $saved = parent::save($options);
        $values = request()->get('values');
        if ($values) {
            $sort = count($values);
            ProductAttrValue::where('attr_id', $this->id)->delete();
            foreach ($values as $value) {
                ProductAttrValue::create([
                    'attr_id' => $this->id,
                    'value' => $value,
                    'sort' => $sort--
                ]);
            }
        }
        return $saved;
    }

    /**
     * 删除时将同时删除ProductAttrValue
     */
    public function delete()
    {
        ProductAttrValue::where('attr_id', $this->id)->delete();
        return parent::delete();
    }

}
