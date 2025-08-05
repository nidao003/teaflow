<?php

namespace App\Http\Controllers\Front;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\ProductStore;
use App\Models\Product;
use App\Models\ProductType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Classes\Store;
use App\Http\Middleware\AuthNoError;

/**
 * @Apidoc\Title("前端-商品")
 */
#[Prefix('api/v1/front/product')]
#[Middleware(AuthNoError::class)]
class ProductController extends \App\Http\Controllers\Controller
{
    /**
    * @Apidoc\Title("查看商品详情")
    * @Apidoc\Tag("商品")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/front/product/view")
    * @Apidoc\Query (name="selected_store_id", type="int", required=true, description="门店ID")
    * @Apidoc\Query (name="id", type="string", required=false, description="商品ID")
    */
    #[Post('view')]
    public function view(Request $request)
    {
        $store_id = $request->input('selected_store_id');
        $id = $request->input('id');
        if (!$store_id) {
            return $this->error("门店ID不能为空");
        }
        if (!$id) {
            return $this->error("商品ID不能为空");
        }
        $product = Product::find($id);
        if (!$product) {
            return $this->error("商品不存在");
        }
        $product->store = ProductStore::where('store_id', $store_id)
                ->where('product_id', $id)
                ->first();
        return $this->success("", $product);
    }

    public function selectAttr($product)
    {

        $product_id = $product->id;
        $res = SelectProductAttr::where('product_id', $product_id)->first();
        if ($res) {
            $arr = $res->attr;
            return $arr;
        }
        return [];
    }
    /**
    * @Apidoc\Title("商品列表")
    * @Apidoc\Tag("商品")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/front/product/list")
    * @Apidoc\Query (name="store_id", type="int", required=true, description="门店ID")
    * @Apidoc\Query (name="title", type="string", required=false, description="商品名称")
    */
    #[Post('list')]
    public function list(Request $request)
    {
        $store_id = $request->input('store_id');
        $title = $request->input('title');
        if (!$store_id) {
            return $this->error("门店ID不能为空");
        }
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $products_ids = ProductStore::where('store_id', $store_id)->pluck('product_id')->toArray();
        $all = Product::where('status', 1)
                    ->whereIn('id', $products_ids);
        if ($title) {
            $all = $all->where('title', 'like', "%$title%");
        }
        $all = $all->orderBy('sort', 'desc')
                    ->orderBy('id', 'asc')
                    ->get();
        $list = [];
        foreach ($all as $vv) {
            $list[] =  [
                'id' => $vv->id,
                'name' => $vv->title,
                'currentPrice' => $vv->show_price,
                'originalPrice' => $vv->show_market_price,
                'image' => $vv->image,
            ];
        }
        return $this->success("", $list);
    }

    /**
     * @Apidoc\Title("分类及商品")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/front/product/cate-list")
     * @Apidoc\Query (name="store_id", type="int", required=true, description="门店ID")
     */
    #[Post('cate-list')]
    public function cateList(Request $request)
    {
        $store_id = $request->input('store_id');
        if (!$store_id) {
            return $this->error("门店ID不能为空");
        }
        /**
         * 读分类
         */
        $all = ProductType::orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get();
        foreach ($all as $key => &$value) {
            //从门店商品表中取绑定的product_id
            $products_ids = ProductStore::where('store_id', $store_id)->pluck('product_id')->toArray();
            $value->products = Product::where('type_id', $value->id)
                    ->where('status', 1)
                    ->whereIn('id', $products_ids)
                    ->orderBy('sort', 'desc')
                    ->orderBy('id', 'asc')
                    ->get();
        }
        $list = [];
        foreach ($all as $v) {
            $products = $v->products;
            $row = ['name' => $v->title];
            foreach ($products as $vv) {
                $row['products'][] = [
                    'id' => $vv->id,
                    'name' => $vv->title,
                    'currentPrice' => $vv->show_price,
                    'originalPrice' => $vv->show_market_price,
                    'image' => $vv->image,
                ];
            }
            if (isset($row['products']) && count($row['products']) > 0) {
                $list[] = $row;
            }
        }
        return $this->success("", $list);
    }

}
