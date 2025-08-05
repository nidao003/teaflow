<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Classes;

use App\Models\Pay as PayModel;
use Illuminate\Support\Facades\Log;
class Pay
{
    /**
     * 创建支付
     * @param $store_id  店铺ID
     * @param $device_id  设备ID
     * @param $order_number 订单号
     * @param $pay_number 第三方支付单号
     * @param $total_amount 金额
     * @param $pay_method 支付方式
     */
    public static function create($opt = [])
    {
        Log::info('创建支付 create ', ['opt' => $opt]);
        $store_id = $opt['store_id'] ?? '';
        $device_id = $opt['device_id'] ?? '';
        $order_number = $opt['order_number'] ?? '';
        $pay_number = $opt['pay_number'] ?? '';
        $total_amount = $opt['total_amount'];
        $pay_method = $opt['pay_method'] ?? 'weixin';
        $data = [];
        $res = PayModel::where('pay_number', $pay_number)
            ->whereOr('order_number', $order_number)
            ->first();
        if ($res) {
            if ($total_amount != $res->total_amount) {
                return [];
            }
            return [
                'pay_id' => $res->id,
                'order_number' => $res->order_number,
                'pay_number' => $res->pay_number,
                'total_amount' => $res->total_amount,
            ];
        }
        $data['order_number'] = $order_number ?: OrderNumber::create();
        $data['pay_number'] = $pay_number;
        $data['total_amount'] = $total_amount;
        $data['paid_amount'] = $total_amount;
        $data['pay_method'] = $pay_method;
        $data['store_id'] = $store_id;
        $data['device_id'] = $device_id;
        $data['pay_status'] = 'completed';
        $data['paid_at'] = date('Y-m-d H:i:s');
        $pay = new PayModel();
        $pay->fill($data);
        $pay->save();
        //写入失败
        if (!$pay->id) {
            Log::error('创建支付失败', ['data' => $data]);
        }
        $res = [];
        $res['order_number'] = $order_number;
        $res['pay_number']   = $pay_number;
        $res['total_amount'] = $total_amount;
        $res['pay_id'] = $pay->id;
        return $res;
    }
}
