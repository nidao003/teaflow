<?php
/**
 * 支付宝支付
 *
 * 开发
 * https://pay.yansongda.cn/docs/v3/quick-start/alipay.html
 *
 * sandbox 环境
 * https://openhome.alipay.com/develop/sandbox/app
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */

namespace App\Classes\Pay;

use Yansongda\Pay\Pay;
use Yansongda\Pay\Contract\HttpClientInterface;
use Illuminate\Support\Facades\Log;
use App\Classes\Setting;

class Alipay extends Base
{
    protected $base_path = 'alipay';
    public function __construct()
    {
        $notify_url = env('APP_URL').'/api/v1/pay/alipay/notify';
        $setting = Setting::get("alipay_*");
        $this->setting = $setting;
        $ali_pro = $setting['alipay_pro'] ?? 0;
        $ali_mode = Pay::MODE_NORMAL;
        if ($ali_pro == -1) {
            $ali_mode = Pay::MODE_SANDBOX;
        }
        $config = [
            'alipay' => [
                'default' => [
                    // 必填-支付宝分配的 app_id
                    'app_id' => $setting['alipay_app_id'] ?? '',
                    // 必填-应用私钥 字符串或路径
                    // 在 https://open.alipay.com/develop/manage 《应用详情->开发设置->接口加签方式》中设置
                    'app_secret_cert' => $setting['alipay_secret_key'] ?? '',
                    // 必填-应用公钥证书 路径
                    // 设置应用私钥后，即可下载得到以下3个证书
                    'app_public_cert_path' => $this->strToFile('app_public_cert', 'alipay_app_public_cert_path'),
                    // 必填-支付宝公钥证书 路径
                    'alipay_public_cert_path' => $this->strToFile('alipay_public_cert', 'alipay_public_cert_path'),
                    // 必填-支付宝根证书 路径
                    'alipay_root_cert_path' => $this->strToFile('alipay_root_cert', 'alipay_root_cert_path'),
                    'return_url' => '',
                    'notify_url' => $notify_url,
                    // 选填-第三方应用授权token
                    'app_auth_token' => '',
                    // 选填-服务商模式下的服务商 id，当 mode 为 Pay::MODE_SERVICE 时使用该参数
                    'service_provider_id' => '',
                    // 选填-默认为正常模式。可选为： MODE_NORMAL, MODE_SANDBOX, MODE_SERVICE
                    'mode' => $ali_mode,
                ]
            ],
            'logger' => [
                'enable' => false,
            ],
            'http' => [ // optional
                'timeout' => 5.0,
                'connect_timeout' => 5.0,
                // 更多配置项请参考 [Guzzle](https://guzzle-cn.readthedocs.io/zh_CN/latest/request-options.html)
            ],
        ];
        $this->pay = Pay::config($config);
    }
    /**
    * 转帐
    * @param $order_num 订单号
    * @param $name 姓名
    * @param $phone 手机号
    * @param $amount 金额
    * @param $desc 备注
    */
    public function transfer($order_num, $name, $phone, $amount, $desc = '转账')
    {
        $option = [
            'out_biz_no' => $order_num,
            'trans_amount' => floatval($amount),
            'payee_info' => [
                'name' => $name,
                'identity' => $phone,
                'identity_type' => 'ALIPAY_LOGON_ID',
            ],
            'biz_scene' => 'DIRECT_TRANSFER',
            'product_code' => 'TRANS_ACCOUNT_NO_PWD',
            'remark' => $desc,
        ];
        $res = Pay::alipay()->transfer($option);
        $res = $res->toArray();
        if ($res['code'] == 10000) {
            $res['code'] = 0;
            return $res;
        } else {
            return false;
        }
    }
    /**
    * 退款
    * @param $out_refund_no 商家退款单号
    * @param $out_trade_no 原订单号
    * @param $refund_amount 退款金额
    * @param $_action  app mini pos scan h5
    */
    public function refund($out_refund_no, $out_trade_no, $refund_amount, $refund_reason = '退款')
    {
        $_action = '';
        if ($info) {
            $_action = $info['action'];
        }
        $req = [
            'out_trade_no' => $out_trade_no,
            'refund_amount' => $refund_amount,
            'refund_reason' => $refund_reason,
            'out_request_no' => $out_refund_no,
            '_action' => $_action,
        ];
        $res = Pay::alipay()->refund($req);
        $res = $res->toArray();
        if ($res['code'] == 10000) {
            return true;
        } else {
            return false;
        }
    }
    /**
    * 被用户扫码
    */
    public function pos($req = [])
    {
        $res = Pay::alipay()->pos($req);
        return $this->returnData($res->toArray());
    }
    /**
     * H5 下单
     *
     * @param array $req
     * @return void
     */
    public function h5($req = [])
    {
        $res = Pay::alipay()->wap($req);
        return (string)$res->getBody();
    }
    /**
    * PC
    * 返回二维码
    */
    public function pc($req = [])
    {
        $req['qr_pay_mode']  = 4;
        $req['product_code'] = 'FAST_INSTANT_TRADE_PAY';
        $res = Pay::alipay()->web($req);
        return (string)$res->getBody();
    }
    /**
     *  APP支付
     */
    public function app($req = [])
    {
        $res = Pay::alipay()->app($req);
        return $this->returnData($res->toArray());
    }
    /**
     * 小程序
     * buyer_id
     */
    public function jsapi($req = [])
    {
        // buyer_id
        $res = Pay::alipay()->mini($req);
        //trade_no 支付宝交易号
        return $this->returnData($res->toArray());
    }

    /**
     * 支付异步通知
     */
    public function notify($order_num, $call)
    {
        $res = $this->query($order_num);
        Log::info('支付宝异步通知', $res);
        if ($res && $res['code'] == 0) {
            Log::info('支付宝异步通知成功信息', $res);
            $data = [
                'order_num' => $order_num,
                'transaction_id' => $res['trade_no'],
                'openid' => $res['buyer_logon_id'],
                'data' => $res,
            ];
            $this->afterPay($data);
            $call($data);
            return Pay::alipay()->success();
        }
    }
    /***
     *  查询订单（商户订单号）
     */
    public function query($order_num)
    {
        $order = [
            'out_trade_no' => $order_num,
        ];
        $res = Pay::alipay()->find($order)->toArray();
        $trade_no = $res['trade_no'];
        Log::info('alipay query', $res);
        if ($res['code'] == 10000 && $res['trade_status'] == 'TRADE_SUCCESS') {
            $ret = [];
            $ret['code'] = 0;
            $ret['order_num'] = $order_num;
            $ret['type'] = 'alipay';
            $ret['amount'] = floatval($res['total_amount']);
            $ret['info'] = $res;
            return $ret;
        } else {
            return [];
        }
    }

    protected function returnData($data)
    {
        if ($data['code'] == 10000) {
            $data['code'] = 0;
            $data['type'] = 'success';
            return $data;
        } else {
            return [
                'code' => 250,
                'message' => $data['sub_msg'] ?? $data['msg'],
                'type' => 'error',
            ];
        }
    }
}
