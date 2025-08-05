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
use App\Models\ArticleType;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @Apidoc\Title("平台-文章类型")
 */
#[Prefix('api/v1/admin/article-type')]
#[Middleware(AdminAuth::class)]
class ArticleTypeController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("列表")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article-type/list")
     *
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $model = ArticleType::orderBy('id', 'desc')->get();
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article-type/page")
     *
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = ArticleType::orderBy('id', 'desc');
        $model = $model->paginate($per_page);
        $index = ($page - 1) * $per_page + 1 ;
        $model->each(function ($item) use (&$index) {
            $item->article_count = $item->article_count;
            $item->index = $index++;
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("创建")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article-type/page")
     *
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $err = $this->validate($request, [
            'title' => 'required|unique:article_types|max:255',
        ]);
        if ($err) {
            return $err;
        }
        $title = $request->input('title');
        $model = ArticleType::create([
            'title' => $title,
        ]);
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("修改")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article-type/update")
     *
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required|exists:article_types',
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
        ArticleType::where('id', $id)->update([
            'title' => $title,
        ]);
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("删除")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article-type/delete")
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
        if (ArticleType::where('id', $id)->first()->article_count > 0) {
            return $this->error("该类型下有文章，不可删除");
        }
        ArticleType::where('id', $id)->delete();
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("获取一条记录")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article-type/view")
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
        if (!$model = ArticleType::where(['id' => $id])->first()) {
            return $this->error("数据不存在");
        }
        return $this->success("", $model);
    }
}
