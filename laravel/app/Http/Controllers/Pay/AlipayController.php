<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Http\Controllers\Pay;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Any;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use Illuminate\Support\Facades\Auth as Author;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use DB;
use App\Classes\Pay\Alipay;

/**
 * @Apidoc\Title("支付-支付宝")
 */
#[Prefix('api/v1/pay/alipay')]
class AlipayController extends \App\Http\Controllers\Controller
{
    protected $pay;
    public function init()
    {
        parent::init();
        $this->pay = new Alipay();
    }
    /**
     * @Apidoc\Title("POS扫码,当面付")
     * @Apidoc\Tag("支付")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/pay/alipay/pos")
     * @Apidoc\Query("order_num", type="string", default="", require=true, desc="订单号")
     * @Apidoc\Query("auth_code", type="string", default="", require=true, desc="付款码")
     * @Apidoc\Query("subject", type="string", default="在线点餐", require=true, desc="付款说明")
     * @Apidoc\Query("total_amount", type="string", default="", require=true, desc="金额，单位元")
     */
    #[Post('pos')]
    public function pos(Request $request)
    {
        $subject = $request->input('subject', '在线点餐');
        $total_amount = $request->input('total_amount', '');
        $order_num = $request->input('order_num', '');
        if (!$total_amount || !$order_num) {
            return $this->error('参数错误');
        }
        if ($total_amount <= 0) {
            return $this->error('金额错误');
        }
        $req = [
            'subject'        => $subject,
            'total_amount'   => $total_amount,
            'out_trade_no'   => $order_num,
        ];
        $auth_code = $request->input('auth_code', '');
        $req['auth_code'] = $auth_code;
        $res = $this->pay->pos($req);
        return $res;
    }
    /**
     * @Apidoc\Title("异步通知")
     * @Apidoc\Tag("支付")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/pay/alipay/notify")
     */
    #[Any('notify/{order_num}')]
    public function notify($order_num, Request $request)
    {
        $order_num = $request->input('order_num') ?: $order_num;
        return $this->pay->notify($order_num, function () use ($order_num) {
            //业务逻辑
            \App\Classes\Order::afterPay($order_num);
        });
    }



}
