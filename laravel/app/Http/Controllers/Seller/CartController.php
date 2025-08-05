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
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use Illuminate\Support\Facades\Auth as Author;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Middleware\SellerAuth;
use Illuminate\Support\Facades\Log;
use App\Models\Cart;
use App\Models\CartItem;
use DB;
use App\Classes\CheckOrderData;
use App\Classes\Store;

/**
 * @Apidoc\Title("商家-购物车")
 */
#[Prefix('api/v1/seller/cart')]
#[Middleware(SellerAuth::class)]
class CartController extends \App\Http\Controllers\Controller
{
    protected $default_device_id = 'default';
    /**
     * @Apidoc\Title("清空商家代客下单购物车成功")
     * @Apidoc\Tag("购物车")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/cart/delete")
     * @Apidoc\Query("_device_id", type="string",require=false, desc="设备号")
     */
    #[Post('delete')]
    public function delete(Request $request)
    {
        $device_id = $request->input('_device_id', $this->default_device_id);
        $store_id = Store::getId();
        if (!$store_id) {
            return $this->error('店铺不存在');
        }
        $cart = Cart::where('store_id', $store_id)
        ->where('device_id', $device_id)
        ->first();
        if (!$cart) {
            return $this->success('购物车数据不存在');
        }
        /**
         * 数据库事务
         */
        DB::beginTransaction();
        try {
            $all = Cart::where('store_id', $store_id)
                ->where('device_id', $device_id)
                ->where('tag', 'seller')
                ->get();
            foreach ($all as $key => $value) {
                CartItem::where('cart_id', $value->id)->delete();
            }
            Cart::where('store_id', $store_id)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('清空商家代客下单购物车失败', ['msg' => $e->getMessage()]);
            return $this->success('清空商家代客下单购物车失败');
        }
        return $this->success('清空商家代客下单购物车成功');
    }


}
