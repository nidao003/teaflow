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
use App\Classes\WeixinApplet;
use App\Models\User;

/**
 * @Apidoc\Title("通用-登录")
 */
#[Prefix('api/v1/login')]
class LoginController extends Controller
{
    /**
     * @Apidoc\Title("小程序手机号授权登录")
     * @Apidoc\Tag("登录")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/login/applet")
     * @Apidoc\Query("code", type="string",require=true, desc="")
     * @Apidoc\Query("iv", type="string",require=true, desc="")
     * @Apidoc\Query("encryptedData", type="string",require=true, desc="")
     *
     *
     * @Apidoc\Returned("token", type="string", desc="token将用于后续请求")
     * @Apidoc\Returned("user_id", type="string", desc="用户ID")
     * @Apidoc\Returned("openid", type="string", desc="")
     * @Apidoc\Returned("type", type="string", desc="帐号类型 user admin seller")
     */
    #[Post('applet')]
    public function applet(Request $request)
    {
        $code = $request->input('code');
        $iv = $request->input('iv');
        $encryptedData = $request->input('encryptedData');
        $err = $this->validate($request, [
            'code' => 'required',
            'iv' => 'required',
            'encryptedData' => 'required',
        ]);
        if ($err) {
            return $err;
        }
        WeixinApplet::init();
        $data = WeixinApplet::login($code, $iv, $encryptedData);
        return $this->success('请求成功', $data);
    }
    /**
     * @Apidoc\Title("帐号密码登录")
     * @Apidoc\Tag("登录")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/login/account")
     * @Apidoc\Query("email", type="string",require=true, desc="邮件地址")
     * @Apidoc\Query("password", type="string",require=true, desc="密码")
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
        return User::loginByEmail($email, $password);
    }
}
