<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Address extends Model
{
    use HasFactory;
    protected $table = 'address';
    protected $fillable = [
        'title',
        'parent_id',
        'sort',
    ];
}
