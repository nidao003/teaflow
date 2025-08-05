<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use Illuminate\Validation\Rule;
use App\Http\Middleware\SellerAuth;
use hg\apidoc\annotation as Apidoc;
use App\Models\User;
use App\Models\Order;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Classes\Pay\Base as PayBase;
use App\Classes\Store;
use App\Classes\Qr;
use Illuminate\Support\Facades\Cache;

/**
 * @Apidoc\Title("商家-QR扫码")
 */
#[Prefix('api/v1/seller/qr')]
#[Middleware(SellerAuth::class)]
class QrController extends \App\Http\Controllers\Controller
{
    /**
    * @Apidoc\Title("解析QR码")
    * @Apidoc\Tag("QR")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/seller/qr/parse")
    * @Apidoc\Query("code", type="string",require=false, desc="")
    * @Apidoc\Query("ignore_uuid", type="string",require=false, desc="")
    */
    #[Post('parse')]
    public function parse(Request $request)
    {
        $code = $request->input('code');
        if (!$code) {
            return $this->error('参数错误');
        }
        $ignore_uuid = $request->input('ignore_uuid', 0);
        $user_qr_rule = Qr::getType('user');
        if (!$ignore_uuid && substr($code, 0, strlen($user_qr_rule)) == $user_qr_rule) {
            $user_id = User::parseQr($code);
            $user = '';
            if ($user_id) {
                $user = User::find($user_id);
                if ($user) {
                    $user = User::outputApiInfo($user);
                }
            }
            return $this->success('请求成功', [
                'type' => 'user',
                'user_id' => $user_id,
                'type' => 'user',
                'user' => $user,
            ]);
        }
        //出餐
        $out_rule = Qr::getType('out');
        if (substr(strtolower($code), 0, strlen($out_rule)) == $out_rule) {
            $arr = Cache::get($code);
            if (!$arr) {
                return $this->error('参数错误');
            }
            $arr = json_decode($arr, true);
            if (!$arr) {
                return $this->error('参数错误');
            }
            $code_store_id = $arr['store_id'];
            $store_id = Store::getId();
            if ($store_id != $code_store_id) {
                return $this->error('参数错误');
            }
            $order_number = $arr['order_number'];
            $order = Order::where('order_number', $order_number)
               ->where('store_id', Store::getId())
               ->first();
            if ($order) {
                Order::where('id', $order->id)
                    ->update([
                        'waiting_at' => date("Y-m-d H:i:s", time()),
                        'order_status' => 'wait_get',
                    ]);
                broadcast(new \App\Events\StoreOrderCountEvent($order->store_id));
            }

            return $this->success('', [
               'type' => 'out',
            ]);
        }
        //取餐
        $in_rule = Qr::getType('take');
        if (substr(strtolower($code), 0, strlen($in_rule)) == $in_rule) {
            $arr = Cache::get($code);
            if (!$arr) {
                return $this->error('参数错误');
            }
            $arr = json_decode($arr, true);
            if (!$arr) {
                return $this->error('参数错误');
            }
            $user_id = $arr['user_id'];
            $order_number = $arr['order_number'];
            $order = Order::where('order_number', $order_number)
               ->where('store_id', Store::getId())
               ->first();
            if ($order) {
                if ($order->user_id != $user_id) {
                    return $this->error('参数错误');
                }
                Order::where('id', $order->id)
                  ->update([
                      'get_time' => date("Y-m-d H:i:s", time()),
                      'order_status' => 'success',
                  ]);
                broadcast(new \App\Events\StoreOrderCountEvent($order->store_id));
            }
            return $this->success('', [
               'type' => 'take',
            ]);
        }
        //支付
        $type = PayBase::getAuthCodeType($code);
        $cart_items = $request->input('data')['cart_items'] ?? [];
        $data = [];
        if ($cart_items) {
            foreach ($cart_items as $item) {
                $data[] = [
                    'product_id' => $item['product_id'],
                    'spec' => $item['spec'],
                    'attr' => $item['attr'],
                    'mix' => $item['mix'],
                    'num' => $item['num'],
                ];
            }
        }
        $store_id = Store::getId();
        $output = [
            'type' => 'paycode',
            'code' => $code,
            'data' => $data,
            'store_id' => $store_id,
        ];
        /*
        broadcast(new \App\Events\StorePaidEvent($order->store_id));
        broadcast(new \App\Events\StoreOrderCountEvent($order->store_id));
        */
        switch ($type) {
            case 'alipay':
                //调用支付宝支付
                $output['title'] = 'alipay';
                return $this->success('请求成功', $output);
                break;
            case 'weixin':
                $output['title'] = 'weixin';
                return $this->success('请求成功', $output);
                break;
            default:
                $output['title'] = 'test';
                return $this->success('请求成功', $output);
                break;
        }
    }




}
