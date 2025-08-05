<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model
{
    use HasFactory;
    protected $table = 'settings';
    protected $fillable = ['title','content','seller_id'];
    protected $casts = [
        'content' => 'array'
    ];
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }
    public static function getSetting($seller_id, $title)
    {
        return self::where('seller_id', $seller_id)
                ->where('title', $title)
                ->first();
    }
}
