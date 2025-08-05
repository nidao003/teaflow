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
use App\Models\ProductAttr;
use App\Models\Order;
use App\Classes\Store;
use Spatie\RouteAttributes\Attributes\Middleware;

/**
 * @Apidoc\Title("商家-订单")
 */
#[Prefix('api/v1/seller/order')]
#[Middleware(SellerAuth::class)]
class OrderController extends \App\Http\Controllers\Controller
{
    use \App\Trait\DataList;
    use \App\Trait\DataForm;
    use \App\Trait\DataShow;
    use \App\Trait\DataDelete;

    public function init()
    {
        parent::init();
        $this->model = 'Order';
        // list
        $this->orderBy = [
            'id' => 'desc',
        ];

        $this->where = [
            ['order_number','like','%{value}%'],
            ['pay_status', '=', '{value}'],
            ['store_id', '=', '{value}'],
        ];
    }
    /**
    * @Apidoc\Title("今日订单")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/seller/order/today")
    */
    #[Post('today')]
    public function today(Request $request)
    {
        $query = Order::where('store_id', Store::getId())
            ->whereBetween('created_at', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
            ->where('pay_status', 'completed')
            ->orderBy('id', 'desc');

        $all = $query->get();
        $total = $query->count();

        //waiting_at字段有值
        $waiting = $query->whereNotNull('waiting_at')->count();

        $count = [
            'total' => $total,
            'waiting' => $total - $waiting,

        ];
        return $this->success('请求成功', [
            'data' => $all,
            'count' => $count,
        ]);
    }

    /**
    * @Apidoc\Title("今日订单统计")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/seller/order/today_count")
    */
    #[Post('today_count')]
    public function todayCount(Request $request)
    {
        $query = Order::where('store_id', Store::getId())
            ->whereBetween('created_at', [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')])
            ->where('pay_status', 'completed');

        $total = $query->count();
        $query_1 = clone $query;
        $query_2 = clone $query;
        //waiting_at字段有值
        $waiting = $query_1->whereNull('waiting_at')->count();
        // get_time 为空 未取餐
        $get_time = $query_2->whereNull('get_time')->count();

        $count = [
            'total' => $total,
            'waiting' => $waiting,
            'get_time' => $get_time,
        ];
        return $this->success('', [
            'count' => $count,
        ]);
    }
    /**
    * @Apidoc\Title("分页")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/seller/order/index")
    * @Apidoc\Query("order_number", type="string",require=false, desc="订单号")
    */
    #[Post('index')]
    public function index(Request $request)
    {
        $request->merge([
            'pay_status' => 'completed',
            'store_id' => Store::getId(),
        ]);
        $data = $this->getDataPage($request, $request->input('per_page', 20));
        return $this->success('请求成功', $data);
    }

    /**
    * @Apidoc\Title("单条记录")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/seller/order/show")
    * @Apidoc\Query("id", type="string",require=false, desc="")
    *
    */
    #[Post('show')]
    public function show(Request $request)
    {
        $data = $this->getDataShow($request);
        return $this->success('', $data);
    }



}
