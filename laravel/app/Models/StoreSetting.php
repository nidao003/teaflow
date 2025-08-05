<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    use HasFactory;

    protected $table = 'store_setting';

    protected $fillable = [
        'store_id',
        'key',
        'value'
    ];

    //不需要时间戳
    public $timestamps = false;


}
