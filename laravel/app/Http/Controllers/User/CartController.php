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
use App\Classes\Coupon;

/**
 * @Apidoc\Title("用户-购物车")
 */
#[Prefix('api/v1/user/cart')]
#[Middleware(Auth::class)]
class CartController extends \App\Http\Controllers\Controller
{
    protected $default_device_id = 'default';
    /**
     * @Apidoc\Title("获取购物车数据")
     * @Apidoc\Tag("购物车")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/cart/index")
     * @Apidoc\Query("_device_id", type="string",require=false, desc="设备号")
     * @Apidoc\Query("user_id", type="string",require=false, desc="用户ID")
     * @Apidoc\Query("store_id", type="string",require=false, desc="门店ID")
     * @Apidoc\Query("in_app", type="string",require=false, desc="在微信小程序中")
     * @Apidoc\Query("is_checked", type="string",require=false, desc="是否只显示已选中的")
     * @Apidoc\Query ("tag",type="string",desc="标签，seller说明是商家代客下单,user说明是用户自己下单"),
     */
    #[Post('index')]
    public function index(Request $request)
    {
        $device_id = $request->input('_device_id', $this->default_device_id);
        $store_id = $this->store_id ?: $request->input('store_id', '');
        if (in_array(Author::user()->type, ['store', 'seller'])) {
            $user_id = $request->input('user_id', '');
        }
        if ($request->input('in_app')) {
            $user_id = Author::user()->id;
        }
        if (!$user_id) {
            return $this->success('未知用户');
        }
        $tag = $request->input('tag', 'user');
        if (!$store_id) {
            $store_id = Store::getId();
        }
        if (!$store_id) {
            Log::error('获取购物车数据：门店ID不能为空');
            return $this->error('门店ID不能为空');
        }
        $cart = Cart::where('store_id', $store_id)->where('tag', $tag);
        if ($user_id) {
            $cart = $cart->where('user_id', $user_id);
        }
        if ($device_id) {
            $cart = $cart->where('device_id', $device_id);
        }
        $cart = $cart->first();
        if (!$cart) {
            return $this->success('购物车为空', ['num' => 0]);
        }
        $is_checked = $request->input('is_checked', 0);
        $query = CartItem::where('cart_id', $cart->id);
        if ($is_checked) {
            $query->where('checked', 1);
        }
        $cart_items = $query->get();
        if (!$cart_items) {
            return $this->success('购物车为空');
        }
        foreach ($cart_items as $cart_item) {
            $cart_item->product = $cart_item->product;
        }
        $num = 0;
        if ($cart_items) {
            $num = count($cart_items);
        }
        $data = [
            'cart' => $cart,
            'cart_items' => $cart_items,
            'num' => $num
        ];
        Coupon::getAmount($cart, $cart_items);
        return $this->success('获取购物车数据成功', $data);
    }
    /**
     * @Apidoc\Title("加入购物车")
     * @Apidoc\Tag("购物车")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/cart/add")
     * @Apidoc\Query("_device_id", type="string",require=false, desc="设备号")
     * @Apidoc\Query("user_id", type="string",require=false, desc="用户ID")
     * @Apidoc\Query("store_id", type="string",require=false, desc="门店ID")
     * @Apidoc\Query ("product_id",type="string",desc="商品ID"),
     * @Apidoc\Query ("spec",type="string",desc="杯型，大杯"),
     * @Apidoc\Query ("attr",type="string",desc="规格名，常温|半糖"),
     * @Apidoc\Query ("mix",type="string",desc="小料，红豆|加浓"),
     * @Apidoc\Query ("num",type="string",desc="数量"),
     * @Apidoc\Query ("tag",type="string",desc="标签，seller说明是商家代客下单,user说明是用户自己下单"),
     */
    #[Post('add')]
    public function add(Request $request)
    {
        $device_id = $request->input('_device_id', $this->default_device_id);
        $store_id = $request->input('store_id', '');
        $product_id = $request->input('product_id', '');
        $spec = $request->input('spec', '');
        $attr = $request->input('attr', '');
        $mix_arr = $mix = $request->input('mix', '');
        $num = $request->input('num', '');
        $user_id = $request->input('user_id', '');
        $tag = $request->input('tag', 'user');
        if (!$store_id) {
            $store_id = Store::getId();
        }
        if ($request->input('in_app')) {
            $user_id = Author::user()->id;
        }
        if (!$user_id) {
            $user_id = $store_id.'-'.time();
        }
        if (!$store_id) {
            Log::error('加入购物车：门店ID不能为空');
            return $this->error('门店ID不能为空');
        }
        if (!$product_id) {
            Log::error('加入购物车：商品ID不能为空');
            return $this->error('商品ID不能为空');
        }
        if (!$spec) {
            Log::error('加入购物车：杯型不能为空');
            return $this->error('杯型不能为空');
        }
        if (!$attr) {
            Log::error('加入购物车：规格名不能为空');
            return $this->error('规格名不能为空');
        }
        if (!$num) {
            Log::error('加入购物车：数量不能为空');
            return $this->error('数量不能为空');
        }
        if (is_array($attr)) {
            $attr = implode('|', $attr);
        }
        if (is_array($mix)) {
            $mix = implode('|', $mix);
        }
        $data = [
            'store_id' => $store_id,
            'device_id' => $device_id,
            'product_id' => $product_id,
            'spec' => $spec,
            'attr' => $attr,
            'mix' => $mix,
            'num' => $num
        ];
        $res = CheckOrderData::check($store_id, [$data]);
        if (is_string($res)) {
            return $this->error($res);
        }
        $data = $res['data'][0];
        DB::beginTransaction();
        try {
            // 先查找购物车
            $cart = Cart::where('user_id', $user_id)
                ->where('store_id', $store_id)
                ->where('device_id', $device_id)
                ->where('tag', $tag)
                ->first();

            // 计算mix_amount总和
            $mix_amount = 0;
            if ($mix_arr) {
                foreach ($mix_arr as $mix_item) {
                    foreach ($data['data']['mix'] as $mix_key => $mix_price) {
                        if ($mix_key == $mix_item) {
                            $mix_amount = bcadd($mix_amount, $mix_price, 2);
                            break;
                        }
                    }
                }
            }
            if ($cart) {
                $cartItem = CartItem::where('cart_id', $cart->id)
                    ->where('product_id', $product_id)
                    ->where('spec', $spec)
                    ->where('attr', $attr)
                    ->where('mix', $mix)
                    ->first();

                if ($cartItem) {
                    // 更新现有CartItem
                    $cartItem->num = $data['num']; // 直接赋值
                    $cartItem->price = $data['price'];
                    $cartItem->total_price = bcmul($cartItem->price, $cartItem->num, 2);
                    $cartItem->mix = $mix; // 保持原样 "红豆|加浓"
                    $cartItem->mix_price = $mix_amount; // 单份mix总价 (11.00)
                    $cartItem->mix_amount = bcmul($cartItem->mix_price, $cartItem->num, 2); // mix总价 * 数量
                    $cartItem->subtotal = bcadd($cartItem->total_price, $cartItem->mix_amount, 2);
                    $cartItem->save();
                } else {
                    // 创建新的CartItem
                    $cartItem = CartItem::create([
                        'cart_id' => $cart->id,
                        'product_id' => $product_id,
                        'spec' => $spec,
                        'attr' => $attr,
                        'mix' => $mix, // "红豆|加浓"
                        'num' => $data['num'],
                        'price' => $data['price'],
                        'total_price' => bcmul($data['price'], $data['num'], 2), // 14.9 * 2 = 29.80
                        'mix_price' => $mix_amount, // 11.00
                        'mix_amount' => bcmul($mix_amount, $data['num'], 2), // 11.00 * 2 = 22.00
                        'subtotal' => bcadd(
                            bcmul($data['price'], $data['num'], 2),
                            bcmul($mix_amount, $data['num'], 2),
                            2
                        ), // 29.80 + 22.00 = 51.80
                    ]);
                }
            } else {
                // 创建新的Cart和CartItem
                $cart = Cart::create([
                    'tag' => $tag,
                    'device_id' => $device_id,
                    'user_id' => $user_id,
                    'store_id' => $store_id,
                    'total_quantity' => 0,
                    'total_amount' => 0,//实际需要支付的金额
                    'total_mix_amount' => 0,
                    'subtotal' => 0,//小计，商品小计，不含小料
                ]);

                $cartItem = CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product_id,
                    'spec' => $spec,
                    'attr' => $attr,
                    'mix' => $mix, // "红豆|加浓"
                    'num' => $data['num'],
                    'price' => $data['price'],
                    'total_price' => bcmul($data['price'], $data['num'], 2), // 14.9 * 2 = 29.80
                    'mix_price' => $mix_amount, // 11.00
                    'mix_amount' => bcmul($mix_amount, $data['num'], 2), // 11.00 * 2 = 22.00
                    'subtotal' => bcadd(
                        bcmul($data['price'], $data['num'], 2),
                        bcmul($mix_amount, $data['num'], 2),
                        2
                    ), // 29.80 + 22.00 = 51.80
                ]);
            }

            // 更新Cart表，从CartItem聚合计算
            $cartTotals = CartItem::where('cart_id', $cart->id)
                ->selectRaw('
                    SUM(num) as total_quantity,
                    SUM(total_price) as subtotal,
                    SUM(mix_amount) as total_mix_amount,
                    SUM(subtotal) as total_amount
                ')
                ->first();

            $cart->total_quantity = $cartTotals->total_quantity ?: '0';
            $cart->total_amount = $cartTotals->total_amount ?: '0.00';
            $cart->total_mix_amount = $cartTotals->total_mix_amount ?: '0.00';
            $cart->subtotal = $cartTotals->subtotal ?: '0.00';
            $cart->tag = $tag;
            $cart->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('加入购物车失败: ' . $e->getMessage(), [
                'input' => compact('user_id', 'store_id', 'device_id', 'tag', 'product_id'),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('加入购物车失败: ' . $e->getMessage());
        }
        return $this->success('加入购物车成功', ['user_id' => $user_id]);
    }
    /**
     * @Apidoc\Title("更新数量")
     * @Apidoc\Tag("购物车")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/cart/update_number")
     * @Apidoc\Query("id", type="string",require=false, desc="item id")
     * @Apidoc\Query("num", type="string",require=false, desc="数量")
     */
    #[Post('update_number')]
    public function update_number(Request $request)
    {
        $id = $request->input('id', '');
        $num = $request->input('num', '');
        if (!$id) {
            return $this->error('id不能为空');
        }
        if ($num < 1) {
            return $this->error('数量有误');
        }
        $cart_item = CartItem::where('id', $id)->first();
        if (!$cart_item) {
            return $this->error('购物车数据不存在');
        }
        if ($request->input('in_app')) {
            $user_id = Author::user()->id;
            if ($user_id != $cart_item->cart->user_id) {
                return $this->error('购物车数据不存在');
            }
        }
        //数据库事务
        DB::beginTransaction();
        try {
            $cart_item->num = $num;
            $cart_item->mix_amount = bcmul($num, $cart_item->mix_price, 2);
            $cart_item->total_price = bcmul($num, $cart_item->price, 2);
            $cart_item->subtotal = bcadd($cart_item->total_price, $cart_item->mix_amount, 2);
            $cart_item->save();
            $total_quantity = CartItem::where('cart_id', $cart_item->cart_id)->sum('num');
            $total_amount = CartItem::where('cart_id', $cart_item->cart_id)->sum('subtotal');
            $total_mix_amount = CartItem::where('cart_id', $cart_item->cart_id)->sum('mix_amount');
            $subtotal = CartItem::where('cart_id', $cart_item->cart_id)->sum('total_price');
            $cart = Cart::where('id', $cart_item->cart_id)->first();
            $cart->total_quantity = $total_quantity;
            $cart->total_amount = $total_amount;
            $cart->total_mix_amount = $total_mix_amount;
            $cart->subtotal = $subtotal;
            $cart->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('更新购物车数据失败:' . $e->getMessage());
            return $this->error('更新购物车数据失败');
        }
        return $this->success('更新购物车数据成功');
    }
    /**
     * @Apidoc\Title("删除某条记录")
     * @Apidoc\Tag("购物车")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/cart/delete_item")
     * @Apidoc\Query("id", type="string",require=false, desc="item id")
     */
    #[Post('delete_item')]
    public function delete_item(Request $request)
    {
        $id = $request->input('id', '');
        if (!$id) {
            return $this->error('id不能为空');
        }
        if (!is_array($id)) {
            $id = [$id];
        }
        $cart_items = CartItem::whereIn('id', $id)->get();
        if ($cart_items->isEmpty()) {
            return $this->error('购物车数据不存在');
        }
        DB::beginTransaction();
        try {
            foreach ($cart_items as $cart_item) {
                if ($request->input('in_app')) {
                    $user_id = Author::user()->id;
                    if ($user_id != $cart_item->cart->user_id) {
                        DB::rollBack();
                        return $this->error('购物车数据不存在');
                    }
                }
                $sub_quantity = $cart_item->num;
                $subtotal = $cart_item->subtotal;
                $mix_amount = $cart_item->mix_amount;
                $cart_id = $cart_item->cart_id;
                $cart_item->delete();
                $cart = Cart::where('id', $cart_id)->first();
                $cart->total_quantity = bcsub($cart->total_quantity, $sub_quantity, 0);
                $cart->total_amount = bcsub($cart->total_amount, $subtotal, 2);
                $cart->total_mix_amount = bcsub($cart->total_mix_amount, $mix_amount, 2);
                $cart->save();
                if (!CartItem::where('cart_id', $cart_id)->exists()) {
                    $cart->delete();
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('更新购物车数据失败:', ['exception' => $e]);
            return $this->error('更新购物车数据失败');
        }
        return $this->success('更新购物车数据成功');
    }

    /**
     * @Apidoc\Title("清空")
     * @Apidoc\Tag("购物车")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/cart/delete")
     * @Apidoc\Query("_device_id", type="string",require=false, desc="设备号")
     * @Apidoc\Query("user_id", type="string",require=false, desc="用户id")
     */
    #[Post('delete')]
    public function delete(Request $request)
    {
        $device_id = $request->input('_device_id', $this->default_device_id);
        $user_id = $request->input('user_id', '');
        if ($request->input('in_app')) {
            $user_id = Author::user()->id;
        }
        if (!$user_id) {
            return $this->error('用户id不能为空');
        }
        $cart = Cart::where('user_id', $user_id)
            ->where('device_id', $device_id)
            ->first();
        if (!$cart) {
            return $this->error('购物车数据不存在');
        }
        //数据库事务
        DB::beginTransaction();
        try {
            //删除
            CartItem::where('cart_id', $cart->id)->delete();
            Cart::where('id', $cart->id)->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('删除购物车数据失败:' . $e->getMessage());
            return $this->error('删除购物车数据失败');
        }
        return $this->success('删除购物车数据成功');
    }

    /**
     * @Apidoc\Title("原购物车数据变更为其他用户的")
     * @Apidoc\Tag("购物车")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/cart/update_user")
     * @Apidoc\Query("user_id", type="string",require=false, desc="用户ID")
     * @Apidoc\Query("new_user_id", type="string",require=false, desc="新用户ID")
     */
    #[Post('update_user')]
    public function update_user(Request $request)
    {
        $new_user_id = $request->input('new_user_id', '');
        $user_id = $request->input('user_id', '');
        if (!$user_id || !$new_user_id) {
            return $this->success('');
        }
        $cart = Cart::where('user_id', $user_id)->first();
        if (!$cart) {
            return $this->success('');
        }
        Cart::where('user_id', $user_id)->update(['user_id' => $new_user_id]);
        return $this->success('操作成功');
    }

    /**
     * @Apidoc\Title("选中或不选中")
     * @Apidoc\Tag("购物车")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/cart/checked")
     * @Apidoc\Query("id", type="string",require=false, desc="")
     * @Apidoc\Query("checked", type="string",require=false, desc="")
     * @Apidoc\Query("user_id", type="string",require=false, desc="用户id")
     */
    #[Post('checked')]
    public function checked(Request $request)
    {
        $id = $request->input('id', '');
        $checked = $request->input('checked', 0);
        $user_id = Author::user()->id;
        if (!$id || !$user_id) {
            return $this->success('');
        }
        $item = CartItem::where('id', $id)->first();
        if (!$item) {
            return $this->success('');
        }
        if ($item->cart->user_id != $user_id) {
            return $this->success('');
        }
        CartItem::where('id', $id)->update(['checked' => $checked]);
        return $this->success('操作成功');
    }

    /**
     * @Apidoc\Title("全选")
     * @Apidoc\Tag("购物车")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/cart/checked_all")
     * @Apidoc\Query("id", type="string",require=false, desc="")
     */
    #[Post('checked_all')]
    public function checked_all(Request $request)
    {
        $user_id = Author::user()->id;
        if (!$user_id) {
            return $this->success('');
        }
        $cart = Cart::where('user_id', $user_id)->first();
        if (!$cart) {
            return $this->success('');
        }
        $checked = 1;
        if ($cart->is_checked_all) {
            $checked = 0;
        }
        CartItem::where('cart_id', $cart->id)->update(['checked' => $checked]);
        return $this->success('操作成功');
    }


}
