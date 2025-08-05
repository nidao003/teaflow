<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use App\Models\User;

/**
 * @Apidoc\Title("通用-注册")
 */
#[Prefix('api/v1/reg')]
class RegController extends Controller
{
    /**
    * @Apidoc\Title("注册帐号")
    * @Apidoc\Tag("注册")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/reg/account")
    * @Apidoc\Query("email", type="string",require=true, desc="邮件地址")
    * @Apidoc\Query("password", type="string",require=true, desc="密码")
    * @Apidoc\Query("type", type="string",require=true, desc="类型 user seller admin")
    *
    *
    * @Apidoc\Returned("token", type="string", desc="token将用于后续请求")
    * @Apidoc\Returned("user_id", type="string", desc="用户ID")
    * @Apidoc\Returned("type", type="string", desc="帐号类型 user admin seller")
    */
    #[Post('account')]
    public function account(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $type = $request->input('type') ?: 'user';
        return User::regByEmail($email, $password, $type);
    }
}
