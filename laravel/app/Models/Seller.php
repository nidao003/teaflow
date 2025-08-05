<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seller extends Model
{
    use HasFactory;
    /**
     * 软删除
     */
    use SoftDeletes;
    protected $table = 'sellers';
    protected $guarded = [];
    protected $hidden = ['password'];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    /**
     * 允许赋值的字段
     */
    protected $fillable = ['title','email','phone','user_id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 关联 store
     *
     */
    public function stores()
    {
        return $this->hasMany(Store::class, 'seller_id', 'id');
    }
    /**
     * 关联单个store
     */
    public function store()
    {
        return $this->hasOne(Store::class, 'seller_id', 'id');
    }
}
