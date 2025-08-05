<?php

namespace App\Classes;

use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use DB;
use App\Classes\Pay\Alipay;
use App\Classes\Store;
use App\Classes\Pay;
use Illuminate\Support\Facades\Log;

trait TraitPaid
{
    public function paidSuccess()
    {
        $request = request();
        $order_number = $request->input('order_number');
        $res = $this->createPayInfo($order_number);
        return $this->success('', $res);
    }

    public function createPayInfo($order_number)
    {
        if (!$order_number) {
            Log::error('TraitPaid 订单号不能为空');
            return $this->error('订单号不能为空');
        }
        $order = Order::where('order_number', $order_number)->first();
        if (!$order) {
            Log::error('TraitPaid 订单不存在');
            return $this->error('订单不存在');
        }
        $paid_amount = $order->paid_amount;
        //支付第三方返回的单号
        $pay_number = mt_rand(1000000000, 9999999999);
        $store_id = $order->store_id;
        /**
         * 生成支付记录
         */
        $res = Pay::create([
            'store_id' => $store_id,
            'pay_method' => 'weixin',
            'order_number' => $order_number,
            'pay_number' => $pay_number,
            'total_amount' => $paid_amount,
        ]);
        $pay_id = $res['pay_id'];
        $order_number = $res['order_number'];
        if (!$pay_id) {
            Log::error('TraitPaid 支付记录创建失败');
        }
        /**
         * 更新订单状态
         */
        $paid_at = date('Y-m-d H:i:s');
        $order = Order::where('order_number', $order_number)->first();
        Order::where('order_number', $order_number)
            ->update([
                'pay_status' => 'completed',
                //待取餐
                'order_status' => 'paid',
                'paid_at' => $paid_at
            ]);
        Log::info('TraitPaid 支付成功，通过事件');
        Log::info('发起打印小票');
        //测试打印小票
        $this->doPrint($order_number);
        /**
         * 支付成功，通过事件
         */
        broadcast(new \App\Events\StorePaidEvent($order->store_id));
        broadcast(new \App\Events\StoreOrderCountEvent($order->store_id));
       
    }
}
