<?php

namespace App\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use Illuminate\Validation\Rule;
use App\Http\Middleware\SellerAuth;
use hg\apidoc\annotation as Apidoc;
use App\Models\ProductAttr;
use App\Classes\Store;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Classes\Time;
use App\Models\Order;
use App\Classes\Price;

/**
 * @Apidoc\Title("商家-订单统计")
 */
#[Prefix('api/v1/seller/order_stat')]
#[Middleware(SellerAuth::class)]
class OrderStatController extends \App\Http\Controllers\Controller
{
    /**
    * @Apidoc\Title("按天统计")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/seller/order_stat/day")
    * @Apidoc\Query("store_id", type="string",require=false, desc="")
    */
    #[Post('day')]
    public function day(Request $request)
    {
        $store_id = Store::getId();
        $all = Order::where('store_id', $store_id)
                ->where('pay_status', 'completed')
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m-%d") as day')
                ->groupBy('day')
                ->orderBy('day', 'desc')
                ->paginate($request->input('per_page', 20));
        foreach ($all->items() as $key => &$value) {
            $query = Order::where('store_id', $store_id)
                ->where('pay_status', 'completed')
                ->where('created_at', 'like', $value->day . '%');
            $query_1 = clone $query;
            $query_2 = clone $query;
            $query_3 = clone $query;
            $value->total_count = $query_1->count();
            $value->total_quantity = $query_2->sum('total_quantity');
            $value->total_amount = $query_3->sum('real_amount');
            $value->day = $value->day ;
        }
        return $this->success('请求成功', $all);

    }
    /**
    * @Apidoc\Title("按月统计")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/seller/order_stat/month")
    * @Apidoc\Query("store_id", type="string",require=false, desc="")
    */
    #[Post('month')]
    public function month(Request $request)
    {
        $store_id = Store::getId();
        $all = Order::where('store_id', $store_id)
                ->where('pay_status', 'completed')
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month')
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->paginate($request->input('per_page', 20));
        foreach ($all->items() as $key => &$value) {
            $query = Order::where('store_id', $store_id)
                ->where('pay_status', 'completed')
                ->where('created_at', 'like', $value->month . '%');
            $query_1 = clone $query;
            $query_2 = clone $query;
            $query_3 = clone $query;
            $value->total_count = $query_1->count();
            $value->total_quantity = $query_2->sum('total_quantity');
            $value->total_amount = $query_3->sum('real_amount');
            $value->month = $value->month . '月';
        }
        return $this->success('请求成功', $all);

    }
    /**
    * @Apidoc\Title("统计")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/seller/order_stat/index")
    * @Apidoc\Query("store_id", type="string",require=false, desc="")
    * @Apidoc\Query("top", type="string",require=false, desc="顶部统计显示数量")
    * @Apidoc\Query("line", type="string",require=false, desc="echart显示天数")
    */
    #[Post('index')]
    public function index(Request $request)
    {
        $show_top = $request->input('top', '');
        $show_line = $request->input('line', '');
        $store_id = Store::getId();
        $all = Time::get('', true);
        $count = [];
        $amount = [];
        foreach ($all as $key => $value) {
            $a = $value[0];
            $b = $value[1];
            $count[$key] = Order::where('store_id', $store_id)
                     ->where('pay_status', 'completed')
                     ->where('created_at', '>=', $a)
                     ->where('created_at', '<=', $b)
                     ->count();
            $price = Order::where('store_id', $store_id)
                    ->where('pay_status', 'completed')
                    ->where('created_at', '>=', $a)
                    ->where('created_at', '<=', $b)
                    ->sum('real_amount');
            $amount[$key] = Price::format($price);
        }
        $top = [
            [
                'title' => '今日订单',
                'count' => $count['today'],
                'amount' => $amount['today'],
                'type' => 'primary'
            ],
            [
                'title' => '昨日订单',
                'count' => $count['yesterday'],
                'amount' => $amount['yesterday'],
                'type' => 'success'
            ],
            [
                'title' => '本周订单',
                'count' => $count['week'],
                'amount' => $amount['week'],
                'type' => 'warning'
            ],
            [
                'title' => '上周订单',
                'count' => $count['lastweek'],
                'amount' => $amount['lastweek'],
                'type' => 'info'
            ],
            [
                'title' => '本月订单',
                'count' => $count['month'],
                'amount' => $amount['month'],
                'type' => 'primary'
            ],
            [
                'title' => '上月订单',
                'count' => $count['lastmonth'],
                'amount' => $amount['lastmonth'],
                'type' => 'success'
            ]
        ];
        if ($show_top > 0) {
            foreach ($top as $key => $value) {
                if ($key >= $show_top) {
                    unset($top[$key]);
                }
            }
        }
        $data['top'] = $top;
        $show_line = $show_line ?: 30;
        $all = Time::date($show_line, '-');
        $count = [];
        $amount = [];
        $dates = [];
        foreach ($all as $key => $value) {
            $a = $value . ' 00:00:00';
            $b = $value . ' 23:59:59';
            $count[] = Order::where('store_id', $store_id)
                     ->where('pay_status', 'completed')
                     ->where('created_at', '>=', $a)
                     ->where('created_at', '<=', $b)
                     ->count() ?: 0;
            $price = Order::where('store_id', $store_id)
                    ->where('pay_status', 'completed')
                    ->where('created_at', '>=', $a)
                    ->where('created_at', '<=', $b)
                    ->sum('real_amount') ?: 0;
            $amount[] = Price::format($price);
            $dates[] = $value;
        }

        $data['line'] = [
            //时间
            'dates' => $dates,
            //订单数量
            'data_1' => $count,
            //订单金额
            'data_2' => $amount,
        ];
        return $this->success('请求成功', $data);
    }




}
