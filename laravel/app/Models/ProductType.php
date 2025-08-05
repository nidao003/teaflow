<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductType extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'product_type';
    protected $fillable = [
        'title',
        'slug',
        'sort',
    ];
    //不需要时间
    public $timestamps = false;
    public function products()
    {
        return $this->hasMany(Product::class, 'type_id', 'id');
    }
    /**
     * product_count
     */
    public function getProductCountAttribute()
    {
        return $this->products()->count();
    }

}
