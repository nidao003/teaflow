<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductBindAttr extends Model
{
    use HasFactory;

    protected $table = 'product_bind_attr';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'product_attr_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productAttr()
    {
        return $this->belongsTo(ProductAttr::class, 'product_attr_id');
    }
}
