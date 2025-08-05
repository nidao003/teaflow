<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Put;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\AdminAuth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @Apidoc\Title("平台-商品")
 */
#[Prefix('api/v1/admin/product')]
#[Middleware(AdminAuth::class)]
class ProductController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("列表")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product/list")
     * @Apidoc\Param("type_id", type="string", require=true, desc="分类id")
     * @Apidoc\Query("title", type="string", desc="标题")
     *
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
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product/page")
     * @Apidoc\Query("title", type="string", desc="标题")
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = Product::orderBy('sort', 'desc')->orderBy('id', 'asc');
        $title = $request->input('title');
        if ($title) {
            $model = $model->where('title', 'like', '%' . $title . '%');
        }
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
     * @Apidoc\Title("创建")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Param("type_id", type="string", require=true, desc="分类id")
     * @Apidoc\Query("title", type="string",require=true, desc="标题")
     * @Apidoc\Param ("content", type="string",require=true, desc="内容")
     * @Apidoc\Param ("image", type="string",require=true, desc="图片")
     * @Apidoc\Param ("specs", type="string",require=true, desc="规格")
     * @Apidoc\Param ("status", type="string",require=true, desc="状态")
     * @Apidoc\Url ("/api/v1/admin/product/create")
     *
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $err = $this->validate($request, [
            'type_id' => 'required',
            'title' => 'required|unique:products',
            'content' => 'required',
            'image' => 'required',
            'specs' => 'required',
            'status' => 'required',
            'type_id' => 'required',
        ]);
        if ($err) {
            return $err;
        }
        $title = $request->input('title');
        $content = $request->input('content');
        $image = $request->input('image');
        $specs = $request->input('specs');
        $status = $request->input('status');
        $type_id = $request->input('type_id');
        if (!$this->checkSpecs($specs)) {
            return $this->error("价格必须大于0");
        }
        $model = Product::create([
            'title' => $title,
            'content' => $content,
            'image' => $image,
            'specs' => $specs,
            'status' => $status,
            'type_id' => $type_id,
        ]);
        Product::updateAttrMix($model->id);
        return $this->success("操作成功", ['id' => $model->id]);
    }
    /**
     * @Apidoc\Title("修改")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product/update")
     * @Apidoc\Param("type_id", type="string", require=true, desc="分类id")
     * @Apidoc\Param("id", type="string", require=true, desc="ID")
     * @Apidoc\Param("title", type="string", require=true, desc="标题")
     * @Apidoc\Param("content", type="string", require=true, desc="内容")
     * @Apidoc\Param("image", type="string", require=true, desc="图片")
     * @Apidoc\Param("specs", type="string", require=true, desc="规格")
     * @Apidoc\Param("status", type="string", require=true, desc="状态")
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required|exists:products',
            'type_id' => 'required',
            'title' => [
                'required',
                Rule::unique('products')->ignore($request->input('id')),
            ],
            'content' => 'required',
            'image' => 'required',
            'specs' => 'required',
        ]);
        if ($err) {
            return $err;
        }
        $id = $request->input('id');
        $title = $request->input('title');
        $content = $request->input('content');
        $image = $request->input('image');
        $specs = $request->input('specs');
        $status = $request->input('status');
        $type_id = $request->input('type_id');
        if (!$this->checkSpecs($specs)) {
            return $this->error("价格必须大于0");
        }
        Product::where('id', $id)->update([
            'title' => $title,
            'content' => $content,
            'image' => $image,
            'specs' => $specs,
            'status' => $status,
            'type_id' => $type_id,
        ]);
        Product::updateAttrMix($id);
        return $this->success("操作成功");
    }

    protected function checkSpecs(&$specs)
    {
        foreach ($specs as $k => $spec) {
            if (!isset($spec['type']) || !$spec['type']) {
                unset($specs[$k]);
                continue;
            }
            //判断价格
            if (!isset($spec['price']) || $spec['price'] <= 0) {
                //返回错误
                return false;
            }
        }
        if (empty($specs)) {
            return false;
        }
        return true;
    }
    /**
     * @Apidoc\Title("删除")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product/delete")
     *
     */
    #[Post('delete')]
    public function delete(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required',
        ]);
        if ($err) {
            return $err;
        }
        $id = $request->input('id');
        Product::where('id', $id)->delete();
        return $this->success("操作成功");
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
     * @Apidoc\Url ("/api/v1/admin/product/up")
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
     * @Apidoc\Url ("/api/v1/admin/product/down")
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
        $where = [
            'id' => $product_id,
        ];
        $model = Product::where($where)->first();
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
     * @Apidoc\Url ("/api/v1/admin/product/upall")
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
     * @Apidoc\Url ("/api/v1/admin/product/downall")
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
        Product::where('id', '>', 0)->update(['status' => $status]);
        if ($status == 1) {
            $message = "全部上架成功";
        } else {
            $message = "全部下架成功";
        }
        return $this->success($message);
    }
}
