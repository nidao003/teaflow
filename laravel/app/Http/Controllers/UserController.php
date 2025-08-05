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
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\Auth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\User;

/**
 * @Apidoc\Title("通用-用户")
 */
#[Prefix('api/v1/user')]
#[Middleware(Auth::class)]
class UserController extends Controller
{
    /**
     * @Apidoc\Title("小程序获取用户信息")
     * @Apidoc\Tag("用户")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/info")
     * @Apidoc\Query("type", type="string",require=true, desc="来源，如 weixin")
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
    #[Post('info')]
    public function info(Request $request)
    {
        $user = Author::user();
        $info = User::outputApiInfo($user);
        return $this->success('请求成功', $info);
    }

    /**
     * @Apidoc\Title("会员二维码")
     * @Apidoc\Tag("用户")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/qr")
     * @Apidoc\Param("data",type="object",desc="",children={
     *     @Apidoc\Param ("qr_url",type="string",desc="二维码地址"),
     * })
     */
    #[Post('qr')]
    public function qr(Request $request)
    {
        $user = Author::user();
        $data['qr_code'] = $user->qr_code;
        $data['qr_url'] = $user->qr_url;
        return $this->success('请求成功', $data);
    }

    /**
     * @Apidoc\Title("解析会员二维码")
     * @Apidoc\Tag("用户")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/parse_qr")
     * @Apidoc\Query("data", type="string",require=true, desc="QR值")
     * @Apidoc\Param("data",type="object",desc="",children={
     *     @Apidoc\Param ("user_id",type="string",desc="用户ID"),
     * })
     */
    #[Post('parse_qr')]
    public function parse_qr(Request $request)
    {
        $code = $request->input('code', '');
        if (!$code) {
            return $this->error('参数错误');
        }
        $user_id = User::parseQr($code);
        if (!$user_id) {
            return $this->error('解析失败');
        }
        $user = User::find($user_id);
        if ($user) {
            $user = User::outputApiInfo($user);
        }
        return $this->success('请求成功', [
            'user_id' => $user_id,
            'code' => $code,
            'user' => $user,
        ]);
    }
    /**
     * @Apidoc\Title("修改生日")
     * @Apidoc\Tag("用户")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/birthday")
     * @Apidoc\Query("date", type="string",require=true, desc="生日")
     */
    #[Post('birthday')]
    public function birthday(Request $request)
    {
        $user = Author::user();
        if ($user->birthday) {
            return $this->error('生日仅可设置一次');
        }
        $date = $request->input('date', '');
        if (!$date) {
            return $this->error('参数错误');
        }
        $user->birthday = $date;
        $user->save();
        return $this->success('设置成功');
    }
}
