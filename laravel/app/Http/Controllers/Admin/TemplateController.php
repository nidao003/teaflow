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
use App\Models\User;
use App\Models\Template;

/**
 * @Apidoc\Title("平台-模板")
 */
#[Prefix('api/v1/admin/template')]
#[Middleware(AdminAuth::class)]
class TemplateController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("保存模板")
     * @Apidoc\Tag("模板")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/template/save")
     */
    #[Post('save')]
    public function save(Request $request)
    {
        $id = $request->input('id');
        $data = $request->input('data');
        Template::where(['id' => $id])->update(['data' => $data]);
        return $this->success("");
    }
    /**
     * @Apidoc\Title("首页模板")
     * @Apidoc\Tag("模板")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/template/home")
     */
    #[Post('home')]
    public function home(Request $request)
    {
        Template::where(['is_home' => 1])->firstOrCreate(['name' => '首页','is_home' => 1]);
        $data = Template::where(['is_home' => 1])->first() ?? [];
        if (!$data->data) {
            $data->data = [];
        }
        return $this->success("", $data);
    }
}
