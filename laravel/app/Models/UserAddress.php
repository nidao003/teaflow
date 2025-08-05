<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    use HasFactory;
    protected $table = 'user_address';

    protected $fillable = [
        'user_id',
        'title',
        'phone',
        'province',
        'city',
        'district',
        'address',
        'is_default',
        'lat',
        'lng',
    ];

    /**
     * 追加字段
     */
    protected $appends = ['select','full_address'];

    /**
     * 获取select字段
     */
    public function getSelectAttribute()
    {
        return [$this->province, $this->city, $this->district];
    }

    public function getFullAddressAttribute()
    {
        $city = $this->city;
        if($city == '市辖区') {
            $city = '';
        }
        return $this->province.$city.$this->district.$this->address;
    }
 
}
