<?php

namespace App\Classes;

use App\Models\Order as OrderModel;
use App\Models\Pay as PayModel; 
use Illuminate\Support\Facades\Log;
/**
 * 订单
 */
class Order
{
    /**
     * 订单支付成功
     */
    public static function afterPay($order_num)
    { 
        Log::info('订单支付成功', ['order_num' => $order_num]);
        $pay = PayModel::where('order_number', $order_num)
            ->first();
        Log::info('订单支付成功 准备更新pay表状态 1');
        if ($pay) {
            $pay->pay_status = 'success';
            $pay->paid_at = date('Y-m-d H:i:s');
            $pay->save();
            Log::info('订单支付成功 准备更新pay表状态 2');
        }
        Log::info('更新订单状态为paid  step 1');
        $order = OrderModel::where('order_number', $order_num)->first();
        if ($order) {
            $order->status = 1;
            $order->order_status = 'paid';
            $order->paid_at = date('Y-m-d H:i:s');
            $order->save();
            Log::info('更新订单状态为paid  step 2');
        }else{
            Log::info('更新订单状态为paid  step 3');
        }
    }

}
