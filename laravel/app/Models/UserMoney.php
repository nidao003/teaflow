<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMoney extends Model
{
    use HasFactory;

    protected $table = 'user_money';

    protected $fillable = [
        'user_id',
        'money',
        'type',
        'remark',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * 根据用户ID写总金额,这个是新的字段，total_amount
     */
    public function getTotalAmount($user_id)
    {
        $total_amount = UserMoney::where('user_id', $user_id)->sum('money');
        return $total_amount;
    }

    /**
     * created_at 格式化
     */
    public function getCreatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

    /**
     * updated_at 格式化
     */
    public function getUpdatedAtAttribute($value)
    {
        return date('Y-m-d H:i:s', strtotime($value));
    }

}
