<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use Illuminate\Support\Facades\Auth as Author;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Middleware\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Cart;
use App\Models\CartItem;
use DB;
use App\Classes\CheckOrderData;
use App\Classes\Store;
use App\Models\Order;
use App\Classes\Qr;
use Illuminate\Support\Facades\Cache;

/**
 * @Apidoc\Title("用户-条码")
 */
#[Prefix('api/v1/user/qr')]
#[Middleware(Auth::class)]
class QrController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("取餐码")
     * @Apidoc\Tag("用户条码")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/qr/take")
     * @Apidoc\Query("order_number", type="string",require=false, desc="订单号")
     */
    #[Post('take')]
    public function take(Request $request)
    {
        $user_id = Author::user()->id;
        $order_number = $request->get('order_number', '');
        if (!$order_number) {
            return $this->error("订单号不能为空");
        }
        $res = Order::where('order_number', $order_number)->first();
        $rule = Qr::getType('take');
        $cacke_key = Qr::getType('take').$order_number;
        Cache::put($cacke_key, json_encode([
            'user_id' => $user_id,
            'order_number' => $order_number,
        ]), 86400);

        if ($res && $res->user_id == $user_id) {
            $qr_url = Qr::output($cacke_key);
            return $this->success('', [
                'code' => $cacke_key,
                'qr_url'=>$qr_url
            ]);
        }
        return $this->error("生成取餐码异常");
    }


}
