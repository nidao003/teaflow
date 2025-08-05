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
use App\Http\Middleware\Auth;
use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use DB;
use App\Classes\Pay\Alipay;
use App\Classes\Store;
use App\Classes\Pay;

/**
 * @Apidoc\Title("支付-扫码检测")
 */
#[Prefix('api/v1/pay/check')]
#[Middleware(Auth::class)]
class CheckController extends \App\Http\Controllers\Controller
{
    use \App\Classes\TraitPrinterFeie;
    use \App\Classes\TraitPaid;
    /**
     * @Apidoc\Title("检测是否支付")
     * @Apidoc\Tag("支付")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/pay/check/index")
     * @Apidoc\Query("_device_id", type="string",require=false, desc="设备号")
     * @Apidoc\Query("order_number", type="string",require=false, desc="订单号")
     */
    #[Post('index')]
    public function index(Request $request)
    {
        $device_id = $request->input('_device_id');
        if (!$device_id) {
            return $this->error('设备号不能为空');
        }
        $store_id = Store::getId();
        if (!$store_id) {
            return $this->error('店铺不存在');
        }
        $cart = Cart::where('store_id', $store_id)
        ->where('device_id', $device_id)
        ->first();
        if (!$cart) {
            return $this->success('');
        }
        $order_number = $request->input('order_number');
        if (!$order_number) {
            return $this->success('');
        }
        $order = Order::where('order_number', $order_number)->first();
        if (!$order) {
            return $this->success('');
        }
        if ($order->pay_status == 'completed') {
            return $this->success('支付成功');
        }
        return $this->success('');
    }


    /**
     * @Apidoc\Title("测试支付成功效果")
     * @Apidoc\Tag("支付")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/pay/check/demo")
     * @Apidoc\Query("_device_id", type="string",require=false, desc="设备号")
     * @Apidoc\Query("order_number", type="string",require=false, desc="订单号")
     * @Apidoc\Query("coupon_id", type="string",require=false, desc="用户conpon_id")
     */
    #[Post('demo')]
    public function demo(Request $request)
    {
        $device_id = $request->input('_device_id');
        if (!$device_id) {
            return $this->error('设备号不能为空');
        }
        $store_id = Store::getId();
        if (!$store_id) {
            return $this->error('店铺不存在');
        }
        return $this->paidSuccess();
    }

}
