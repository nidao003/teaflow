<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SelectProductAttr extends Model
{
    use HasFactory;

    public $table = 'user_select_product_attr';

    protected $fillable = [
        'user_id',
        'product_id',
        'attr',
    ];

    public $casts = [
        'attr' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
