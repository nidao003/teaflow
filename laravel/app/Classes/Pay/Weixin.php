<?php
/**
 * 微信支付
 *
 * 开发
 * https://easywechat.com/6.x/pay/index.html
 *
 * 官方文档
 * https://pay.weixin.qq.com/wiki/doc/apiv3/index.shtml
 */

namespace App\Classes\Pay;

use EasyWeChat\Pay\Application;
use EasyWeChat\Pay\Message;
use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Classes\Setting;


class Weixin extends Base
{ 
    public $app;
    public $config;
    public $appid;
    protected $base_path = 'weixin';
    public $currency = 'CNY';
    public $notify_url;
    public function __construct()
    {
        $this->notify_url = env('APP_URL').'/api/v1/pay/weixin/notify';
        $setting = Setting::get(["weixin_pay_*",'WEIXIN_APPLET_APPID']);
        $this->setting = $setting;
        $config = [
            'mch_id' => $setting['weixin_pay_mch_id'],
            // 商户证书
            'private_key' => $this->strToFile('private_key', 'weixin_pay_key_path'),
            'certificate' => $this->strToFile('certificate', 'weixin_pay_cert_path'),
            // v3 API 秘钥
            'secret_key' => $setting['weixin_pay_secret_key'],
            // v2 API 秘钥
            'v2_secret_key' => $setting['weixin_pay_v2_secret_key'],
            // 平台证书：微信支付 APIv3 平台证书，需要使用工具下载
            // 下载工具：https://github.com/wechatpay-apiv3/CertificateDownloader
            'platform_certs' => [
                $this->strToFile('platform_cert', 'weixin_pay_platform_cert'),
            ],
            'http' => [
                'throw'  => true, // 状态码非 200、300 时是否抛出异常，默认为开启
                'timeout' => 5.0,
            ],
        ];
        $this->appid = $setting['WEIXIN_APPLET_APPID'];
        $this->app = new Application($config);
        $this->config = $config;
    }

    /**
     * H5 下单
     *
     * @param array $req
     * @return void
     */
    public function h5($req = [])
    {
        return $this->common($req, 'h5');
    }
    /**
     * Native 下单
     *
     * @param array $req
     * @return void
     */
    public function native($req = [])
    {
        return $this->common($req, 'native');
    }
    /**
     * Native 下单
     *
     * @param array $req
     * @return void
     */
    public function jsapi($req = [])
    {
        return $this->common($req, 'jsapi');
    }
    /**
     *  JSAPI下单
     *  jsapi
     *  native
     *  h5
     *  app
     */
    public function common($req = [
        'openid'      => '',
        'subject'        => '',
        'total_amount'   => '',
        'order_num' => '',
        'trade_type'  => 'NATIVE'
    ], $pay_drive = 'jsapi')
    {
        $openid    = $req['openid'];
        $subject      = $req['subject'];
        $total_amount = $req["total_amount"] * 100;
        if (!$total_amount) {
            return ['message' => '金额错误','code' => 250,'type' => 'error'];
        }
        $order_num = $req['order_num'];
        /**
         * https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_1.shtml
         * 如果要改造分帐、开票需要在这里生成支付订单时处理
         */
        $appid = $this->appid;
        $pay_config = [
            'appid' => $appid,
            'mchid' => $this->config['mch_id'],
            'out_trade_no' => $order_num,
            'notify_url' => $this->notify_url.'/'.$order_num,
            "amount" => [
                "total" => (int)$total_amount,
                "currency" => $this->currency
            ],
            //'re_user_name'=> '',
            'description' => $subject,
            //交易结束时间
            //'time_expire'=>'',
        ];
        if ($openid) {
            $pay_config['payer']['openid'] = $openid;
        }
        //H5
        if ($pay_drive == 'h5') {
            unset($pay_config['payer']);
            $pay_config["scene_info"] = [
                "payer_client_ip" => get_ip(),
                "h5_info" => [
                    "type" => "Wap",
                ]
            ];
        }
        $res = $this->app->getClient()->postJson('v3/pay/transactions/' . $pay_drive, $pay_config)->toArray(false);
        if (isset($res['code_url'])) {
            //生成支付二维码
            // $res['code_url']
            //header('Content-Type: '.$result->getMimeType());
            //echo $result->getString();
            return $res['code_url'];
        }
        //除了H5其他的都有prepay_id
        if (isset($res['prepay_id']) || isset($res['h5_url'])) {
            $prepayId = $res['prepay_id'];
            $signType = 'RSA'; // 默认RSA，v2要传MD5
            if ($pay_drive != 'h5') {
                $config   = $this->app->getUtils()->buildBridgeConfig($prepayId, $appid, $signType);
            }
            //H5返回支付URL
            if ($pay_drive == 'h5') {
                return $res['h5_url'];
            }
            return ['data' => $config, 'res' => $res,'code' => 0];
        } else {
            return ['message' => $res['message'], 'res' => $res,'code' => 250,'type' => 'error'];
        }
    }
    /**
     *
     * 支付异步通知
     */
    public function notify($order_num, $call)
    {
        $app = $this->app;
        $server = $app->getServer();
        $server->handlePaid(function (\EasyWeChat\Pay\Message $message, \Closure $next) use ($app, $call) {
            $out_trade_no = $message->out_trade_no;
            $transaction_id = $message->transaction_id;
            $openid = $message->payer['openid'];
            $data = [
                'order_num' => $out_trade_no,
                'transaction_id' => $transaction_id,
                'openid' => $openid,
                'data' => $message->toArray(),
            ];
            
            $query_res = $this->query($out_trade_no);
            Log::info('查寻支付情况：'. $query_res); 
            $cache_key = 'weipay_notice'.$out_trade_no;
            $has_cache = Cache::get($cache_key);
            if($query_res) {
                if(!$has_cache){
                    Log::info('====== 支付查寻订单成功，写入支付表,并调用打印功能 ======'); 
                    Cache::remember($cache_key, now()->addHour(), function () use ($data) {
                        $this->afterPay($data);
                        return true; // 标记为已处理
                    }); 
                }  
            }else{
                Log::info('支付查寻订单失败，无法写入支付表');
            }

            try {
                $app->getValidator()->validate($app->getRequest()); 
                $call($data); 
                Log::info('微信支付异步通知  ------  成功', $data); 
            } catch (\Exception $e) {
                Log::error('微信支付异步通知 xxxxxx  失败', [
                    'out_trade_no' => $out_trade_no,
                    'transaction_id' => $transaction_id,
                    'openid' => $openid,
                    'data' => $message->toArray(),
                ]);  
            }
            return $next($message);
        });
        // 处理退款结果事件
        $server->handleRefunded(function (Message $message, \Closure $next) {
            // $message->out_trade_no 获取商户订单号
            // $message->payer['openid'] 获取支付者 openid
            $out_trade_no = $message->out_trade_no;
            $transaction_id = $message->transaction_id; 
            return $next($message);
        });
        return $server->serve();
    }
    /***
     *  查询订单（商户订单号）
     */
    public function query($outTradeNo)
    {
        $response = $this->app->getClient()->get("v3/pay/transactions/out-trade-no/{$outTradeNo}", [
            'query' => [
                'mchid' =>  $this->app->getMerchant()->getMerchantId()
            ]
        ]);
        $res = $response->toArray();
        if (strtoupper($res['trade_state']) == 'SUCCESS') {
            return true;
        } else {
            return false;
        } 
    }


    /**
     * 退款查寻
     * https://pay.weixin.qq.com/wiki/doc/apiv3/apis/chapter3_1_10.shtml
     * @param $out_refund_no 退款单号
     */
    public function refundQuery($out_refund_no)
    {
        $res = $this->app->getClient()->get("v3/refund/domestic/refunds/{$out_refund_no}", [])->toArray();
        if ($res['status'] == 'SUCCESS' || $res['status'] == 'PROCESSING') {
            $res['code']  = 0;
        } else {
            $res['code']  = 250;
        }
        return $res;
    }
    /**
     * 退款
     * @param $out_refund_no 新的退款单号
     * @param $out_trade_no 原订单号
     * @param $total_fee 总金额
     * @param $refund_amount 退款金额
     * @param $refund_desc 退款描述
     */
    public function refund($out_refund_no, $out_trade_no, $total_fee, $refund_amount, $refund_desc = '退款')
    {
        $fee = $refund_amount;
        $totalFee = (int)($total_fee * 100);
        $refundFee = (int)($refund_amount * 100);
        $opt = [
            'out_refund_no' => $out_refund_no,
            'out_trade_no' => $out_trade_no,
            'amount' => [
                'refund'   => $refundFee,
                'total'    => $totalFee,
                "currency" => $this->currency,
            ],
            'reason' => $out_trade_no,
        ];
        $res = $this->app->getClient()->postJson("v3/refund/domestic/refunds", $opt)->toArray(false);
        if ($res['status'] == 'PROCESSING') {
            $res['status'] = 'ok';
        } else {
            $res['status'] = 'err';
        }
        return $res;
    }
    /**
     * 企业付款到零钱
     * 不是 【企业转帐到零钱】
     * 这是两个产品，新开的微信支付只有 企业付款到零钱。
     * @param $openid 用户openid
     * @param $order_num 订单号
     * @param $amount 金额
     * @param $desc 备注
     */
    public function transfer($openid, $order_num, $amount = 0.01, $desc = '测试')
    {
        $response = $this->app->getClient()->post('/mmpaymkttransfers/promotion/transfers', [
            'body' => [
                'mch_appid' => $this->app_id,     //注意在配置文件中加上app_id
                'mchid' => $this->config['mch_id'],         //商户号
                'partner_trade_no' => $order_num,  // 商户订单号，需保持唯一性(只能是字母或者数字，不能包含有符号)
                'openid' => $openid,     //用户openid
                'check_name' => 'NO_CHECK',                  // NO_CHECK：不校验真实姓名, FORCE_CHECK：强校验真实姓名
                //'re_user_name' => '用户真实姓名',                  // 如果 check_name 设置为 FORCE_CHECK 则必填用户真实姓名
                'amount' => (int)($amount * 100),                              //金额
                'desc' => $desc,                                // 企业付款操作说明信息。必填
            ],
        ]);
        $res = $response->toArray();
        return $res;
    }
    /**
    * 扫用户付款码
    * https://pay.weixin.qq.com/wiki/doc/api/micropay.php?chapter=9_10&index=1
    * @param array $req
    * order_num 订单号
    * total_amount 金额,单位元
    * auth_code 付款码
    * subject 商品描述
    * device_no 扫码设备
    */
    public function pos($req = [])
    {
        $order_num = $req['order_num'];
        $total_fee = $req['total_amount'];
        $auth_code = $req['auth_code'];
        $body = $req['subject'];
        //扫码设备
        $device_info = $req['device_no'] ?: "sn2024";
        $appid = $this->appid;
        $total_fee = bcmul($total_fee, 100, 2);
        $ip =  request()->getClientIp();
        $pay_config = [
            'appid' => $appid,
            'mch_id' => $this->config['mch_id'],
            'device_info' => $device_info,
            'body' => $body,
            'out_trade_no' => $order_num,
            'total_fee' => (int)$total_fee,
            "spbill_create_ip" => $ip,
            'auth_code' => $auth_code,
        ];
        $res = $this->app->getClient()->post('/pay/micropay', [
            'body' => $pay_config
        ])->toArray(false);
        if ($res['return_code'] == 'SUCCESS') {
            $res['code'] = 0;
            $res['type'] = 'success';
        } else {
            $res['code'] = 250;
            $res['type'] = 'error';
        }
        return $res;
    }



}
