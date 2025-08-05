<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductStore extends Model
{
    use HasFactory;
    protected $table = 'product_store';
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    protected $fillable = [
        'product_id',
        'store_id',
        'seller_id',
        'status'
    ];
    /**
     * 关联 product
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    /**
     * 关联 store
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }
    /**
     * 关联 seller
     */
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
    /**
     * 检查是否可以购买
     */
    public static function checkCanBuy($store_id, $product_id, $spec = '', $num = 1)
    {
        $product_store = ProductStore::where('store_id', $store_id)
            ->where('product_id', $product_id)
            ->where('status', 1)
            ->first();
        if (!$product_store) {
            return false;
        }
        return true;
    }

}
