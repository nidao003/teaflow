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
use App\Classes\Store;
use Spatie\RouteAttributes\Attributes\Middleware;

/**
 * @Apidoc\Title("商家-出餐扫码")
 */
#[Prefix('api/v1/seller/order_scan')]
#[Middleware(SellerAuth::class)]
class OrderScanController extends \App\Http\Controllers\Controller
{
    use \App\Trait\DataList;
    use \App\Trait\DataForm;
    use \App\Trait\DataShow;

    public function init()
    {
        parent::init();
        $this->model = 'Order';
        // list
        $this->orderBy = [
            'id' => 'desc',
        ];

        $this->where = [
            ['store_id', '=', Store::getId()],
            ['order_number','like','%{value}%'],
            ['pay_status', '=', 'completed'],
            ['order_status', '=', 'pending'],
        ];
    }
    /**
    * @Apidoc\Title("等待出餐")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/seller/order_scan/index")
    * @Apidoc\Query("order_number", type="string",require=false, desc="订单号")
    */
    #[Post('index')]
    public function index(Request $request)
    {
        $data = $this->getDataList($request);
        return $this->success('请求成功', $data);
    }

    /**
    * @Apidoc\Title("扫码出餐并提醒用户")
    * @Apidoc\Tag("订单")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/seller/order/scan")
    * @Apidoc\Query("code", type="string",require=false, desc="小票二维码")
    *
    */
    #[Post('scan')]
    public function show(Request $request)
    {
        return $this->success('');
    }



}
