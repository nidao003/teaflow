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
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\AdminAuth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\ProductType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @Apidoc\Title("平台-商品类型")
 */
#[Prefix('api/v1/admin/product-type')]
#[Middleware(AdminAuth::class)]
class ProductTypeController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("列表")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product-type/list")
     *
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $all = ProductType::orderBy('sort', 'desc')
            ->orderBy('id', 'asc')
            ->get();
        $index = 1 ;
        $all->each(function ($item) use (&$index) {
            $item->product_count = $item->product_count;
            $item->index = $index++;
        });
        return $this->success("", $all);
    }
    /**
     * @Apidoc\Title("创建")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product-type/create")
     * @Apidoc\Param("title", type="string", require=true, desc="标题")
     * @Apidoc\Param("slug", type="string", require=false, desc="标签")
     *
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $err = $this->validate($request, [
            'title' => 'required|unique:product_type|max:255',
        ]);
        if ($err) {
            return $err;
        }
        $title = $request->input('title');
        $slug = $request->input('slug');
        $model = ProductType::create([
            'title' => $title,
            'slug' => $slug,
        ]);
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("修改")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product-type/update")
     * @Apidoc\Param("id", type="string", require=true, desc="ID")
     * @Apidoc\Param("title", type="string", require=true, desc="标题")
     * @Apidoc\Param("slug", type="string", require=false, desc="标签")
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required|exists:product_type',
            'title' => [
                'required',
                Rule::unique('article_types')->ignore($request->input('id')),
            ],
        ]);
        if ($err) {
            return $err;
        }
        $id = $request->input('id');
        $title = $request->input('title');
        $slug = $request->input('slug');
        ProductType::where('id', $id)->update([
            'title' => $title,
            'slug' => $slug,
        ]);
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("删除")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product-type/delete")
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
        if (ProductType::where('id', $id)->first()->product_count > 0) {
            return $this->error("请先删除该类型下的商品");
        }
        ProductType::where('id', $id)->delete();
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("获取一条记录")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product-type/view")
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
        if (!$model = ProductType::where(['id' => $id])->first()) {
            return $this->error("数据不存在");
        }
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("排序")
     * @Apidoc\Tag("商品")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/product-type/sort")
     * @Apidoc\Param("data",type="array",desc="排序数据",children={
     *     @Apidoc\Param ("id",type="string",desc="id"),
     * })
    */
    #[Post('sort')]
    public function sort(Request $request)
    {
        $data = $request->input('data');
        if (!$data) {
            return $this->error("参数错误");
        }
        $count = count($data);
        foreach ($data as $key => $value) {
            ProductType::where('id', $value['id'])->update(['sort' => $count--]);
        }
        return $this->success("操作成功");
    }
}
