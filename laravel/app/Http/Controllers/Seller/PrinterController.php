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
use DB;
use App\Classes\CheckOrderData;
use App\Classes\Store;
use App\Classes\Setting;
use App\Classes\Printer\Feie;
use App\Models\Order;
use App\Classes\StoreSetting;

/**
 * @Apidoc\Title("商家-打印机")
 */
#[Prefix('api/v1/seller/printer')] 
class PrinterController extends \App\Http\Controllers\Controller
{
    use \App\Classes\TraitPrinterFeie;
    /**
     * @Apidoc\Title("测试打印")
     * @Apidoc\Tag("打印机")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/printer/test")
     * @Apidoc\Query("order_number", type="string",require=false, desc="订单号")
     */
    #[Post('test')]
    public function test(Request $request)
    {
        return [
            'errcode' => 0,
            'errmsg' => 'OK'
        ];
        $order_number = $request->get('order_number', '');
        if (!$order_number) {
            return $this->error('订单号不能为空');
        }
        return $this->doPrint($order_number);
    }


}
