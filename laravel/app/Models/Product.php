<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'products';
    protected $fillable = [
        'title',
        'image',
        'content',
        'specs',
        'status',
        'type_id',
    ];
    /**
     * 商品规格是json
     */
    protected $casts = [
        'specs' => 'array'
    ];

    //appends
    protected $appends = [
        'attr',
        'mix',
        'attr_list',
        'mix_list',
        'show_price',
        'show_market_price',
        'default_select_attr',
    ];

    public function getDefaultSelectAttrAttribute()
    {
        $default = [];
        $specs = $this->specs;
        $first = $specs[0]['type'] ?? '';
        if ($first) {
            $default[] = $first;
        }
        $attr_list = $this->attr_list;
        if ($attr_list) {
            foreach ($attr_list as $v) {
                $attr = $v['values'][0]['value'] ?? '';
                if ($attr) {
                    $default[] = $attr;
                }
            }
        }
        $res = SelectProductAttr::where('product_id', $this->id);
        $user = Auth::user();
        if (!$user) {
            return $default;
        }
        $user_id = $user->id;
        if ($user_id) {
            $res = $res->where('user_id', $user_id);
        }
        $res = $res->first();
        if ($res) {
            return $res->attr;
        }
        return $default;
    }

    public function getShowPriceAttribute()
    {
        return $this->specs[0]['price'] ?? 0;
    }

    public function getShowMarketPriceAttribute()
    {
        return $this->specs[0]['market_price'] ?? 0;
    }

    public function getAttrListAttribute()
    {
        return ProductAttr::whereIn('id', $this->attr)->get();
    }

    public function getMixListAttribute()
    {
        return ProductMix::whereIn('id', $this->mix)->get();
    }

    public function getAttrAttribute()
    {
        return ProductBindAttr::where('product_id', $this->id)->pluck('product_attr_id')->toArray();
    }

    public function getMixAttribute()
    {
        return ProductBindMix::where('product_id', $this->id)->pluck('product_mix_id')->toArray();
    }

    public function type()
    {
        return $this->belongsTo(ProductType::class, 'type_id', 'id');
    }
    /**
     * 显示分类名称
     */
    public function getTypeTitleAttribute()
    {
        return $this->type->title;
    }

    /**
     * 静态方法： 获取所有商品，不包含删除的，按sort排序
     */
    public static function getAll()
    {
        return self::where('status', 1)->orderBy('sort', 'desc')->orderBy('id', 'asc')->get();
    }
    /**
     * 商家绑定的商品
     */
    public function sellerProduct()
    {
        return $this->hasOne(ProductStore::class, 'product_id', 'id')->where('seller_id', \App\Classes\Seller::getId())
            ->orderBy('id', 'desc');
    }
    /**
     * 门店绑定的商品
     */
    public function storeProduct()
    {
        return $this->hasOne(ProductStore::class, 'product_id', 'id')->where('store_id', \App\Classes\Store::getId())
            ->orderBy('id', 'desc');
    }

    public function productMix()
    {
        return $this->hasMany(ProductMix::class, 'product_id', 'id');
    }

    public function productAttr()
    {
        return $this->hasMany(ProductAttr::class, 'product_id', 'id');
    }

    public static function updateAttrMix($id)
    {
        if (!$id) {
            return false;
        }
        $attr = request()->get('attr') ?? [];
        $mix = request()->get('mix') ?? [];
        if ($attr) {
            ProductBindAttr::where('product_id', $id)->delete();
            foreach ($attr as $attr_id) {
                $item = [];
                $item['product_id'] = $id;
                $item['product_attr_id'] = $attr_id;
                $validator = \Validator::make($item, [
                    'product_id' => 'required',
                    'product_attr_id' => 'required',
                ]);
                if ($validator->fails()) {
                    continue;
                }
                ProductBindAttr::create($item);
            }
        }
        if ($mix) {
            ProductBindMix::where('product_id', $id)->delete();
            foreach ($mix as $mix_id) {
                $item = [];
                $item['product_id'] = $id;
                $item['product_mix_id'] = $mix_id;
                $validator = \Validator::make($item, [
                    'product_id' => 'required',
                    'product_mix_id' => 'required',
                ]);
                if ($validator->fails()) {
                    continue;
                }
                ProductBindMix::create($item);
            }
        }

    }


}
