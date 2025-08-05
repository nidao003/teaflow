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

/**
 * @Apidoc\Title("平台-用户")
 */
#[Prefix('api/v1/admin/user')]
#[Middleware(AdminAuth::class)]
class UserController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("分页显示")
     * @Apidoc\Tag("用户")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/user/page")
     * @Apidoc\Query("title", type="string",require=true, desc="邮件或手机号")
     * @Apidoc\Query("email", type="string",require=true, desc="邮件")
     *
     * @Apidoc\Returned("id", type="string", desc="用户ID")
     * @Apidoc\Returned("phone", type="string", desc="手机号")
     * @Apidoc\Returned("oauth",type="object",desc="oauth",children={
     *     @Apidoc\Param ("openid",type="string",desc=""),
     *     @Apidoc\Param ("country_code",type="string",desc=""),
     *     @Apidoc\Param ("phone",type="string",desc=""),
     *     @Apidoc\Param ("pure_phone",type="string",desc=""),
     *     @Apidoc\Param ("unionid",type="string",desc=""),
     *     @Apidoc\Param ("expires_in",type="string",desc=""),
     * })
     *
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $this->validate($request, [
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1',
        ]);
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = User::orderBy('id', 'desc');
        $title = $request->input('title');
        if ($title) {
            $model = $model->where('phone', 'like', "%$title%")->orWhere('email', 'like', "%$title%");
        }
        $email = $request->input('email');
        if ($email) {
            $model = $model->where('email', $email);
        }
        $model = $model->paginate($per_page);
        $index = ($page - 1) * $per_page + 1 ;
        $model->each(function (&$item) use (&$index) {
            $item->type_label = $item->type_label;
            $item->type_color = $item->type_color;
            $item->index = $index++;
            $item->total_amount = $item->total_amount;
        });
        return $this->success("", $model);
    }
}
