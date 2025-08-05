<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use App\Http\Middleware\Auth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Address;
use App\Classes\Arr;

/**
 * @Apidoc\Title("通用-地址库")
 */
#[Prefix('api/v1/address')]
class AddressController extends Controller
{
    /**
     * @Apidoc\Title("获取省市区地址")
     * @Apidoc\Tag("地址")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/address/index")
     */
    #[Post('index')]
    public function index(Request $request)
    {
        /**
         * Address orderby sort desc id asc
         */
        $data = Address::select('id', 'parent_id', 'sort')
            // title as label
            ->addSelect(\DB::raw("title as label"))
            ->addSelect(\DB::raw("title as value"))
            ->orderBy('sort', 'desc')->orderBy('id', 'asc')->get();
        $data = $data->toArray();
        $data = Arr::toTree($data);
        $data = $data[0]['children'];
        return $this->success('请求成功', $data);
    }


}
