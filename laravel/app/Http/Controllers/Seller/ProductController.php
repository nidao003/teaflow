<?php

namespace App\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Put;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\SellerAuth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\ProductStore;
use App\Classes\Store;

/**
 * @Apidoc\Title("商家-商品")
 */
#[Prefix('api/v1/seller/product')]
#[Middleware(SellerAuth::class)]
class ProductController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("列表")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/product/list")
     * @Apidoc\Param("type_id", type="string", require=true, desc="分类id")
     * @Apidoc\Query("title", type="string", desc="标题")
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $type_id = $request->input('type_id');
        $model = Product::orderBy('sort', 'desc')->orderBy('id', 'asc');
        if ($type_id) {
            $model = $model->where(['type_id' => $type_id]);
        }
        $title = $request->input('title');
        if ($title) {
            $model = $model->where('title', 'like', '%' . $title . '%');
        }
        $model = $model->get();
        $index = 1;
        $model->each(function ($item) use (&$index) {
            $item->index = $index++;
            $storeProduct = $item->storeProduct;
            $item->up_down_status = $storeProduct->status ?? -1;
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/product/page")
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = Product::orderBy('sort', 'desc')->orderBy('id', 'asc');
        $model = $model->paginate($per_page);
        $index = ($page - 1) * $per_page + 1 ;
        $model->each(function ($item) use (&$index) {
            $item->article_count = $item->article_count;
            $item->type_title = $item->type_title;
            $item->index = $index++;
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("获取一条记录")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product/view")
     * @Apidoc\Query("id", type="string",require=true, desc="id")
     */
    #[Post('view')]
    public function view(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required',
        ]);
        if ($err) {
            return $err;
        }
        $id = $request->input('id');
        if (!$model = Product::where(['id' => $id])->first()) {
            return $this->error("数据不存在");
        }
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("上架")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/product/up")
     * @Apidoc\Query("product_id", type="string",require=true, desc="商品ID")
     */
    #[Post('up')]
    public function up(Request $request)
    {
        return $this->upOrDown($request, 1);
    }
    /**
     * @Apidoc\Title("下架")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/product/down")
     * @Apidoc\Query("product_id", type="string",require=true, desc="商品ID")
     */
    #[Post('down')]
    public function down(Request $request)
    {
        return $this->upOrDown($request, -1);
    }
    /**
     * 上下架业务逻辑
     */
    protected function upOrDown(Request $request, $status = 1)
    {
        $err = $this->validate($request, [
            'product_id' => 'required',
        ]);
        if ($err) {
            return $err;
        }
        $product_id = $request->input('product_id');
        if (!$this->seller_id) {
            return $this->error("缺少商家信息");
        }
        if (!$this->store_id) {
            return $this->error("缺少门店信息");
        }
        $where = [
            'product_id' => $product_id,
            'seller_id' => $this->seller_id,
            'store_id' => $this->store_id,
        ];
        if (!$model = ProductStore::where($where)->first()) {
            $model = new ProductStore($where);
            $model->status = $status;
            $model->save();
        }
        $model->status = $status;
        $model->save();
        if ($status == 1) {
            $message = "上架成功";
        } else {
            $message = "下架成功";
        }
        return $this->success($message);
    }
    /**
     * @Apidoc\Title("全部上架")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/product/upall")
     * @Apidoc\Query("product_id", type="string",require=true, desc="商品ID")
     */
    #[Post('upall')]
    public function upall(Request $request)
    {
        return $this->upOrDownAll($request, 1);
    }
    /**
     * @Apidoc\Title("全部下架")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/product/downall")
     * @Apidoc\Query("product_id", type="string",require=true, desc="商品ID")
     */
    #[Post('downall')]
    public function downall(Request $request)
    {
        return $this->upOrDownAll($request, -1);
    }
    /**
     * 上下架业务逻辑
     */
    protected function upOrDownAll(Request $request, $status = 1)
    {
        if (!$this->seller_id) {
            return $this->error("缺少商家信息");
        }
        if (!$this->store_id) {
            return $this->error("缺少门店信息");
        }
        $where = [
            'seller_id' => $this->seller_id,
            'store_id' => $this->store_id,
        ];
        $all = Product::orderBy('sort', 'desc')->orderBy('id', 'asc')->get();
        $id = [];
        $all->each(function ($item) use (&$id, $status, $where) {
            $id = $item->id;
            $condition = $where;
            $condition['product_id'] = $id;
            if (ProductStore::where($condition)->first()) {
                ProductStore::where($where)->update(['status' => $status]);
            } else {
                $model = new ProductStore();
                $model->seller_id = $where['seller_id'];
                $model->store_id = $where['store_id'];
                $model->product_id = $id;
                $model->status = $status;
                $model->save();
            }
        });
        if ($status == 1) {
            $message = "全部上架成功";
        } else {
            $message = "全部下架成功";
        }
        return $this->success($message);
    }
}
