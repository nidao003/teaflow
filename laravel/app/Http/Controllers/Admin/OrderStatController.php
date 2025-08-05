<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use Illuminate\Validation\Rule;
use App\Http\Middleware\AdminAuth;
use hg\apidoc\annotation as Apidoc;
use App\Models\ProductAttr;
use App\Classes\Store;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Classes\Time;
use App\Models\Order;
use App\Models\Store as StoreModel;
use App\Classes\Price;

/**
 * @Apidoc\Title("平台-订单统计")
 */
#[Prefix('api/v1/admin/order_stat')]
#[Middleware(AdminAuth::class)]
class OrderStatController extends \App\Http\Controllers\Controller
{
    /**
    * @Apidoc\Title("按天统计")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/admin/order_stat/day")
    * @Apidoc\Query("store_id", type="string",require=false, desc="")
    */
    #[Post('day')]
    public function day(Request $request)
    {
        $all = Order::where('pay_status', 'completed')
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m-%d") as day,store_id')
                ->groupBy('day')
                ->groupBy('store_id')
                ->orderBy('day', 'desc')
                ->paginate($request->input('per_page', 20));
        foreach ($all->items() as $key => &$value) {
            $store_id = $value->store_id;
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
            $value->store_id = $store_id;
            $value->store_info = StoreModel::whereCache(['id' => $store_id]);
        }
        return $this->success('请求成功', $all);

    }
    /**
    * @Apidoc\Title("按月统计")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/admin/order_stat/month")
    * @Apidoc\Query("store_id", type="string",require=false, desc="")
    */
    #[Post('month')]
    public function month(Request $request)
    {
        $all = Order::where('pay_status', 'completed')
                ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month,store_id')
                ->groupBy('month')
                ->groupBy('store_id')
                ->orderBy('month', 'desc')
                ->paginate($request->input('per_page', 20));
        foreach ($all->items() as $key => &$value) {
            $store_id = $value->store_id;
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
            $value->store_id = $store_id;
            $value->store_info = StoreModel::whereCache(['id' => $store_id]);
        }
        return $this->success('请求成功', $all);

    }
    /**
    * @Apidoc\Title("统计")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/admin/order_stat/index")
    * @Apidoc\Query("top", type="string",require=false, desc="顶部统计显示数量")
    * @Apidoc\Query("line", type="string",require=false, desc="echart显示天数")
    * @Apidoc\Query("is_list", type="string",require=false, desc="是否显示表格")
    */
    #[Post('index')]
    public function index(Request $request)
    {
        $show_top = $request->input('top', '');
        $show_line = $request->input('line', '');
        $all = Time::get('', true);
        $count = [];
        $amount = [];
        foreach ($all as $key => $value) {
            $a = $value[0];
            $b = $value[1];
            $count[$key] = Order::where('pay_status', 'completed')
                     ->where('created_at', '>=', $a)
                     ->where('created_at', '<=', $b)
                     ->count();
            $price = Order::where('pay_status', 'completed')
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
            $count[] = Order::where('pay_status', 'completed')
                     ->where('created_at', '>=', $a)
                     ->where('created_at', '<=', $b)
                     ->count() ?: 0;
            $price = Order::where('pay_status', 'completed')
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
        $is_list = $request->input('is_list', '');
        if ($is_list) {
            $all = Order::where('pay_status', 'completed')->groupBy('store_id');
            $all = $all->orderBy('created_at', 'desc')->paginate($request->input('pre_page', 10));
            if ($all) {
                foreach ($all->items() as $key => &$value) {
                    $real_amount = Order::where("store_id", $value->store_id)->where('pay_status', 'completed')->sum('real_amount');
                    $total_quantity = Order::where("store_id", $value->store_id)->where('pay_status', 'completed')->sum('total_quantity');
                    $total_mix_amount = Order::where("store_id", $value->store_id)->where('pay_status', 'completed')->sum('total_mix_amount');
                    $value->total_amount = Price::format($real_amount);
                    $value->total_quantity = $total_quantity;
                    $value->total_mix_amount = $total_mix_amount;
                    $value->store_title = \App\Models\Store::where('id', $value->store_id)->value('title') ?: ' ';
                }
            }
            $data['table'] = $all;
        }
        return $this->success('请求成功', $data);
    }




}
