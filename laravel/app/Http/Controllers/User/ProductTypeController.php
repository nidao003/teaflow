<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\ProductType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @Apidoc\Title("用户-商品类型")
 */
#[Prefix('api/v1/user/product-type')]
class ProductTypeController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("列表")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/product-type/list")
     * @Apidoc\Query("append_all", type="string",require=false, desc="是否添加一个全部")
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $append_all = $request->input('append_all', 0);
        $all = ProductType::orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get();
        $list = [];
        if ($append_all) {
            $list[] = ['id' => 0, 'title' => '全部商品'];
        }
        $all->each(function ($item) use (&$index, &$list) {
            if ($item->product_count > 0) {
                $item->product_count = $item->product_count;
                $list[] = $item;
            }
        });
        return $this->success("", $list);
    }

}
