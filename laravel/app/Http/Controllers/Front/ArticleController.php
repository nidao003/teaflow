<?php

namespace App\Http\Controllers\Front;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Article;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * @Apidoc\Title("前端-文章")
 */
#[Prefix('api/v1/front/article')]
class ArticleController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("显示")
     * @Apidoc\Tag("文章")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/front/article/view")
     * @Apidoc\Query("title", type="string",require=false, desc="标题")
     */
    #[Post('view')]
    public function view(Request $request)
    {
        $title = $request->input('title');
        if (!$title) {
            return $this->error("参数错误");
        }
        $model = Article::where(['title' => $title])->first();
        if (!$model) {
            return $this->error("数据不存在");
        }
        return $this->success("", $model);
    }

}
