<?php

namespace App\Http\Controllers\Admin;

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
use App\Classes\Arr;

/**
 * @Apidoc\Title("平台-订单")
 */
#[Prefix('api/v1/admin/order')]
#[Middleware(Auth::class)]
class OrderController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("销售排序")
     * @Apidoc\Tag("订单")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/order/stat_sort")
     * @Apidoc\Query("day", type="int",require=false, desc="最近天数")
     * @Apidoc\Query("month", type="int",require=false, desc="this_month last_month")
     */
    #[Post('stat_sort')]
    public function StatSort(Request $request)
    {
        $month = $request->input('month', '');
        $day = $request->input('day', 0);
        $all = Order::orderBy('id', 'desc');
        //paid_at不为空
        $all = $all->whereNotNull('paid_at');
        if ($day > 0) {
            $all = $all->where('created_at', '>=', date('Y-m-d H:i:s', strtotime("-$day day")));
        }
        if ($month == 'this_month') {
            $all = $all->where('created_at', '>=', date('Y-m-01 00:00:00'));
        } elseif ($month == 'last_month') {
            $all = $all->where('created_at', '>=', date('Y-m-01 00:00:00', strtotime("-1 month")));
            $all = $all->where('created_at', '<', date('Y-m-01 00:00:00'));
        }
        /**
         * 以store_id分组
         * 以 paid_amount 计算销售额
         * 以 id 计算订单数量
         */
        $list = $all->select(DB::raw('store_id, sum(paid_amount) as paid_amount, count(id) as order_count'))
            ->groupBy('store_id')
            ->get();
        if ($list) {
            foreach ($list as $key => &$value) {
                $value->title = $value->store->title ?? "";
                $value->paid_amount_float = floatval($value->paid_amount);
                $value->paid_amount = number_format($value->paid_amount, 2);
            }
        }
        return $this->success("", $list);
    }

    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("订单")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/order/page")
     * @Apidoc\Query("order_number", type="string",require=false, desc="订单号")
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $order_number = $request->input('order_number', '');
        $all = Order::orderBy('id', 'desc');
        if ($order_number != '') {
            $all = $all->where('order_number', $order_number);
        }
        $list = $all->paginate($request->input('per_page', 10));
        foreach ($list->items() as &$order) {
            $order->store;
        }
        return $this->success("", $list);
    }




}
