<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderPrinter extends Model
{
    use HasFactory;

    protected $table = 'order_printer';

    protected $fillable = [
        'order_number',
        'printer_no',
        'status',
        'type',
    ];
}
