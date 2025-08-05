<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductBindMix extends Model
{
    use HasFactory;

    protected $table = 'product_bind_mix';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'product_mix_id',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productMix()
    {
        return $this->belongsTo(ProductMix::class, 'product_mix_id');
    }
}
