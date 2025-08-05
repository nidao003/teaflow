<?php

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
use App\Models\Article;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @Apidoc\Title("平台-文章")
 */
#[Prefix('api/v1/admin/article')]
#[Middleware(AdminAuth::class)]
class ArticleController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("列表")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article/list")
     *
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $model = Article::orderBy('id', 'desc')->get();
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article/page")
     *
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = Article::orderBy('id', 'desc');
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
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article/create")
     *
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $err = $this->validate($request, [
            'title' => [
                'required',
                Rule::unique('articles'),
            ],
            'body' => 'required',
            'type_id' => 'required|exists:article_types,id',
        ]);
        if ($err) {
            return $err;
        }
        $title = $request->input('title');
        $body = $request->input('body');
        $type_id = $request->input('type_id');
        $model = Article::create([
            'title' => $title,
            'body' => $body,
            'type_id' => $type_id,
        ]);
        return $this->success("操作成功", ['id' => $model->id]);
    }
    /**
     * @Apidoc\Title("修改")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article/update")
     *
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required|exists:articles',
            'title' => [
                'required',
                Rule::unique('articles')->ignore($request->input('id')),
            ],
            'body' => 'required',
            'type_id' => 'required|exists:article_types,id',
        ]);
        if ($err) {
            return $err;
        }
        $id = $request->input('id');
        $title = $request->input('title');
        $body = $request->input('body');
        $type_id = $request->input('type_id');
        Article::where('id', $id)->update([
            'title' => $title,
            'body' => $body,
            'type_id' => $type_id,
        ]);
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("删除")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article/delete")
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
        Article::where('id', $id)->delete();
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("获取一条记录")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/article/view")
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
        if (!$model = Article::where(['id' => $id])->first()) {
            return $this->error("数据不存在");
        }
        return $this->success("", $model);
    }
}
