<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Classes\Distance;

class Store extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'stores';
    public $distance;
    /**
     * append field
     */
    protected $appends = ['seller_title', 'seller_label', 'full_address','area_str'];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'area' => 'array',
    ];
    protected $fillable = [
        'seller_id',
        'title',
        'image',
        'type',
        'phone',
        'area',
        'address',
        'lat',
        'lng',
        'user_id',
        'tea_cost_time',
    ];
    public function seller()
    {
        return $this->belongsTo(Seller::class, 'seller_id', 'id');
    }
    /**
    * 取seller_title字段
    */
    public function getSellerTitleAttribute()
    {
        return $this->seller->title;
    }
    /**
     * 取seller_label，是seller的title + phone字段
     */
    public function getSellerLabelAttribute()
    {
        return $this->seller->title . ' （' . $this->seller->phone.' ）';
    }
    /**
     * full_address
     */
    public function getFullAddressAttribute()
    {
        $area = $this->area;
        if (is_array($area)) {
            $area = implode(' ', $this->area);
        }
        $str = $area . $this->address;
        $str = str_replace(' ', '', $str);
        return $str;
    }

    public function getAreaStrAttribute()
    {
        $str = implode(' ', $this->area);
        $str = str_replace('市辖区', '', $str);
        return $str;
    }

    /**
     * 收货地址是否在门店配送范围
     */
    public function inArea($user_lat, $user_lng)
    {
        $is_in = false;
        if ($this->lat && $this->lng) {
            $store_lat = $this->lat;
            $store_lng = $this->lng;
            $distance = Distance::get($user_lng, $user_lat, $store_lng, $store_lat);
            if ($distance <= $this->area_range) {
                $is_in = true;
            }
        }
        return $is_in;
    }

    /**
     * 是否支持购买
     * 判断 是否在营业时间内
     */
    public function getInWorkTimeAttribute()
    {
        if ($this->is_open != 1) {
            return false;
        }
        if (!$this->open_at || !$this->close_at) {
            return false;
        }
        $now = time();
        $open_at = strtotime($this->open_at);
        $close_at = strtotime($this->close_at);
        if ($now > $open_at && $now < $close_at) {
            return true;
        }
        return false;
    }

}
