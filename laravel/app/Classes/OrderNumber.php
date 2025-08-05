<?php
/**
 * godruoyi/php-snowflake
 */

namespace App\Classes;

use App\Models\Order;

class OrderNumber
{
    public static function create()
    {
        $order_num = self::snowflake();
        while (self::check($order_num)) {
            $order_num = self::snowflake();
        }
        return $order_num;
    }

    public static function check($order_num)
    {
        $order = Order::where('order_number', $order_num)->first();
        if ($order) {
            return true;
        } else {
            return false;
        }
    }

    public static function snowflake()
    {
        $snowflake = new \Godruoyi\Snowflake\Snowflake();
        return $snowflake->id();
    }
}
