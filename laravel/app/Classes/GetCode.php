<?php

namespace App\Classes;

use App\Models\Order;

class GetCode
{
    public static function create($type = 'self-pickup')
    {
        if ($type == 'self-pickup') {
            $code = "A";
        } else {
            $code = "B";
        }
        //取数据库最后一条数据
        $order = Order::where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->orderBy('id', 'desc')
            ->first();
        if ($order) {
            $numberStart = substr($order->get_code, 1) + 1;
        } else {
            $numberStart = 100;
        }
        while (self::check($numberStart)) {
            $numberStart++;
        }
        return $code.$numberStart;
    }

    public static function check($get_code)
    {
        return Order::where('get_code', $get_code)
            ->where('created_at', '>=', now()->startOfDay())
            ->orderByDesc('id')
            ->exists();
    }
    
}
