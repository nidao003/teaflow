<?php

namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\ProductStore;
use App\Models\Product;
use App\Models\SelectProductAttr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Classes\Store;
use App\Http\Middleware\AuthNoError;

/**
 * @Apidoc\Title("用户-商品")
 */
#[Prefix('api/v1/user/product')]
#[Middleware(AuthNoError::class)]
class ProductController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("列表")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/product/list")
     * @Apidoc\Query("store_id", type="string",require=true, desc="门店ID")
     * @Apidoc\Query("title", type="string",require=false, desc="标题")
     * @Apidoc\Query("type_id", type="string",require=false, desc="分类ID")
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $title = $request->input('title', '');
        $store_id = $request->input('store_id', 0);
        if (!$store_id) {
            $store_id = Store::getId();
        }
        if (!$store_id) {
            return $this->error('门店ID不能为空');
        }
        $type_id = $request->input('type_id', 0);
        $all = ProductStore::orderBy('id', 'asc');
        if ($store_id > 0) {
            $all = $all->where('store_id', $store_id);
        }
        $list = $all->get();

        $up_down_status = [];
        foreach ($list as $key => $value) {
            $up_down_status[$value->product_id] = $value->status;
        }

        if (!$list) {
            return $this->error('商品不存在');
        }
        $in = $list->pluck('product_id')->toArray();
        $list = Product::whereIn('id', $in)
                ->orderBy('sort', 'desc')
                ->orderBy('id', 'asc')
                ->where('status', 1);
        if ($title) {
            $list = $list->where('title', 'like', '%' . $title . '%');
        }
        if ($type_id > 0) {
            $list = $list->where('type_id', $type_id);
        }
        $list = $list->get();
        foreach ($list as $key => $value) {
            $list[$key]->up_down_status = $up_down_status[$value->id] ?: 1;
        }
        return $this->success("", $list);
    }

    /**
     * @Apidoc\Title("记住选择的属性")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/product/remember-select-attr")
     * @Apidoc\Query("product_id", type="string",require=true, desc="商品ID")
     * @Apidoc\Query("attr", type="string",require=true, desc="数组")
     */
    #[Post('remember-select-attr')]
    public function rememberSelectAttr(Request $request)
    {
        $product_id = $request->input('product_id');
        $attr = $request->input('attr');
        if (!$product_id || !$attr) {
            return $this->error('参数错误');
        }
        $res = SelectProductAttr::where('product_id', $product_id)->where('user_id', Author::user()->id)->first();
        if (!$res) {
            $res = new SelectProductAttr();
            $res->product_id = $product_id;
            $res->user_id = Author::user()->id;
            $res->attr = $attr;
            $res->save();
            return $this->success('');
        } else {
            $res->attr = $attr;
            $res->save();
            return $this->success('');
        }
    }
}
