<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Order;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Middleware\Auth;
use App\Models\OrderItem;
use App\Models\OrderPay;
use App\Models\OrderDelivery;
use App\Classes\OrderNumber;
use Illuminate\Support\Facades\Log;
use App\Models\Store;
use App\Classes\MapTiandi;
use App\Models\ProductStore;
use App\Models\Product;
use App\Classes\GetCode;
use DB;
use App\Classes\CheckOrderData;
use App\Models\CartItem;
use Carbon\Carbon;

/**
 * @Apidoc\Title("用户-订单")
 */
#[Prefix('api/v1/user/order')]
#[Middleware(Auth::class)]
class OrderController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("生成订单号")
     * @Apidoc\Tag("订单")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/order/create_order_number")
     * @Apidoc\Returned("order_number", type="string", desc="订单号")
     */
    #[Post('create_order_number')]
    public function createOrderNumber(Request $request)
    {
        return $this->success('', ['order_number' => OrderNumber::create()]);
    }
    /**
     * @Apidoc\Title("生成订单")
     * @Apidoc\Tag("订单")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/order/create")
     * @Apidoc\Query("store_id", type="string",require=false, desc="门店ID")
     * @Apidoc\Query("coupon_id", type="string",require=false, desc="优惠券ID")
     * @Apidoc\Query("order_number", type="string",require=false, desc="订单号")
     * @Apidoc\Query("is_delivery", type="string",require=false, desc="是否需要配送，1需要，0不需要")
     * @Apidoc\Query("address", type="string",require=false, desc="收货地址，当需要配送时")
     * @Apidoc\Query("note", type="string",require=false, desc="订单备注")
     * @Apidoc\Query("progress", type="string",require=false, desc="pre时不创建订单，直接返回")
     *
     * @Apidoc\Param("data",type="array",desc="商品数据",children={
     *     @Apidoc\Param ("product_id",type="string",desc="商品ID"),
     *     @Apidoc\Param ("spec",type="string",desc="杯型，大杯"),
     *     @Apidoc\Param ("attr",type="string",desc="规格名，常温|半糖"),
     *     @Apidoc\Param ("mix",type="string",desc="小料，红豆|加浓"),
     *     @Apidoc\Param ("num",type="string",desc="数量"),
     * })
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $progress = $request->input('progress', 'create');
        $store_id = $this->store_id ?: $request->input('store_id', '');
        $order_number = $request->input('order_number', '');
        if (!$store_id) {
            Log::error('下单：门店ID不能为空');
            return $this->error('门店ID不能为空');
        }
        $store = Store::idCache($store_id);
        if (!$store) {
            Log::error('下单：门店不存在');
            return $this->error('门店不存在');
        }
        if (!$store->is_open) {
            Log::error('下单：该门店暂未营业');
            return $this->error('该门店暂未营业');
        }
        if (!$store->in_work_time) {
            Log::error('下单：不在营业时间范围');
            return $this->error('不在营业时间范围');
        }
        $is_delivery = $request->input('is_delivery', '');
        if ($is_delivery == 1) {
            if ($store->is_delivery != 1) {
                Log::error('下单：该门店不支持配送');
                return $this->error('该门店不支持配送');
            }
            $address = $request->input('address', '');
            if (!$address) {
                Log::error('下单：收货地址不能为空');
                return $this->error('收货地址不能为空');
            }
            $map = MapTiandi::get($address);
            $lat = $map['lat'] ?? "";
            $lng = $map['lng'] ?? "";
            if (!$address || !$lat || !$lng) {
                Log::error('下单：经纬度不能为空');
                return $this->error('收货地址不能为空');
            }
            if (!$store->inArea($lat, $lng)) {
                Log::error('下单：不在配送范围');
                return $this->error('不在配送范围');
            }
        }

        $get_code = GetCode::create();
        if (!$get_code) {
            Log::error('下单：生成取餐码失败');
            return $this->error('生成取餐码失败');
        }
        $order_number = $order_number ?: OrderNumber::create();
        if (!$order_number) {
            Log::error('下单：订单号生成失败');
            return $this->error('订单号生成失败');
        }

        $order = Order::where('order_number', $order_number)->first();
        if ($order) {
            return $this->success('');
        }

        $data = $request->input('data', []);
        if (empty($data)) {
            Log::error('异常下单：商品数据不能为空');
            return $this->error('商品数据不能为空');
        }
        $goods = $data;
        $res = CheckOrderData::check($store_id, $data);
        if (is_string($res)) {
            return $this->error($res);
        }
        $total_quantity = $res['total_quantity'];
        $total_amount = $res['total_amount'];
        $total_mix_amount = $res['total_mix_amount'];
        $data = $res['data'];
        $total_amount = bcadd($total_amount, $total_mix_amount, 2);

        //优惠券
        $coupon_id = $request->input('coupon_id');
        $cart_items = $goods;
        if ($progress == 'pre') {
            $goods = $goods[0];
            //说明是直接购买
            $goods['product'] = Product::find($goods['product_id']);
            $attr = $goods['attr'] ?? '';
            if (is_array($attr)) {
                $goods['attr'] = implode('|', $attr);
            }
            $goods['str'] = CartItem::getStr($goods['spec'], $goods['attr'], $goods['mix']);
            $cart_items = [$goods];
        }
        $cart = [
            'total_amount' => $total_amount,
            'total_quantity' => $total_quantity,
            'total_mix_amount' => $total_mix_amount,
            'checked_total_amount' => $total_amount,
            'checked_total_mix_amount' => $total_mix_amount,
        ];
        //实付
        $paid_amount = $total_amount;
        if ($coupon_id) {
            \App\Classes\Coupon::getAmount($cart, $cart_items);
            $paid_amount = $cart['checked_total_amount'];
            //抵扣金额
            $coupon_sub_amount = $cart['sub_amount'];
        }
        $user_id = $request->input('user_id', Author::user()->id);
        if ($progress == 'pre') {
            $cart['total_amount'] = $paid_amount;
            return $this->success("查价成功", [
                'num' => $cart_items[0]['num'],
                'cart' => $cart,
                'cart_items' => $cart_items
            ]);
        }
        /**
         * 数据库事务
         */
        DB::beginTransaction();
        try {
            $order = Order::create([
                'order_number' => $order_number,
                'store_id' => $store_id,
                'user_id' => $user_id,
                'address' => $address ?? '',
                'delivery_method' => $is_delivery ? 'takeout' : 'self-pickup',
                'total_quantity' => $total_quantity,
                'total_amount' => $total_amount,
                'paid_amount' => $paid_amount,
                'real_amount' =>  $paid_amount,
                'refunded_amount' => 0,
                'order_status' => 'pending',
                'pay_status' => 'pending',
                'delivery_status' => 'pending',
                'note' => $request->input('note', ''),
                'get_code' => $get_code,
                'total_mix_amount' => $total_mix_amount,
            ]);
            foreach ($data as $key => $value) {
                $product_id = $value['product_id'];
                $num = $value['num'];
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product_id,
                    'product_title' => $value['product_title'],
                    'product_image' => $value['product_image'],
                    'quantity' => $num,
                    'price' => $value['price'],
                    'subtotal' => $value['subtotal'],
                    'spec' => $value['spec'],
                    'attr' => $value['attr'],
                    'mix' => $value['mix'],
                    'data' => $value['data'],
                    'mix_amount' => $value['mix_amount'],
                ]);
            }
            if ($coupon_id) {
                \App\Classes\Coupon::use($user_id, $order->id, $coupon_id, $coupon_sub_amount);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('下单异常:' . $e->getMessage());
            return $this->error('下单异常');
        }
        return $this->success("下单成功", [
            'order_number' => $order_number,
            'paid_amount' => $paid_amount,
            'id' => $order->id
        ]);
    }

    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("订单")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/order/page")
     * @Apidoc\Query("order_number", type="string",require=false, desc="订单号")
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $date = $request->input('date', 'today');
        $order_number = $request->input('order_number', '');
        $all = Order::orderBy('id', 'desc');
        $all = $all->where('user_id', Author::user()->id);
        if ($order_number != '') {
            $all = $all->where('order_number', $order_number);
        }
        if ($date == 'today') {
            $all = $all->whereDate('created_at', date('Y-m-d'));
        }
        if ($date == 'history') {
            //created_at < 今天
            $all = $all->whereDate('created_at', '<', date('Y-m-d'));
        }
        $list = $all->paginate($request->input('per_page', 10));
        foreach ($list->items() as $key => &$value) {
            $value->store = $value->store;
        }
        return $this->success("", $list);
    }

    /**
     * @Apidoc\Title("查看")
     * @Apidoc\Tag("订单")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/order/view")
     * @Apidoc\Query("id", type="string",require=false, desc="")
     */
    #[Post('view')]
    public function view(Request $request)
    {
        $id = $request->input('id', '');
        $res = Order::where('user_id', Author::user()->id)
                ->where('id', $id)
                ->first();
        if (!$res) {
            return $this->error("订单不存在");
        }
        $res->store = $res->store;
        $res->coupon = $res->coupon->coupon->coupon ?? '';
        return $this->success("", $res);
    }

    /**
     * @Apidoc\Title("删除")
     * @Apidoc\Tag("订单")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/order/delete")
     * @Apidoc\Query("id", type="string",require=false, desc="")
     */
    #[Post('delete')]
    public function delete(Request $request)
    {
        $id = $request->input('id', '');
        $res = Order::where('user_id', Author::user()->id)
                ->where('id', $id)
                ->first();
        if ($res) {
            $res->delete();
        }
        return $this->success("");
    }


    /**
     * @Apidoc\Title("取前台排队人数")
     * @Apidoc\Tag("订单")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/order/people")
     * @Apidoc\Query("selected_store_id", type="string",require=true, desc="门店id")
     * @Apidoc\Query("order_id", type="string",require=false, desc="")
     */
    #[Post('people')]
    public function people(Request $request)
    {
        $store_id = $request->input('selected_store_id', '');
        $order_id = $request->input('order_id', '');
        $store = Store::idCache($store_id);
        if (!$store) {
            return $this->error("门店不存在");
        }
        $count = Order::where('store_id', $store_id)
            ->where('order_status', 'paid')
            ->whereDate('created_at', Carbon::today())
            ->whereNull('waiting_at');
        if ($order_id) {
            //id < $order_id
            $count = $count->where('id', '<=', $order_id);
        }
        $count = $count->count() ?: 0;
        $time = $store->tea_cost_time;
        if (!$time || $time <= 0) {
            $time = 2;
        }
        if ($count > 0) {
            $time = $time * $count;
        }

        return $this->success("", [
            'count' => $count,
            'time' => $time,
            'real_count' => $count > 0? $count - 1 : 0
        ]);
    }

}
