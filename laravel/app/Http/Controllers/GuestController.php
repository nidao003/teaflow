<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use App\Classes\WeixinApplet;
use App\Models\User;

/**
 * @Apidoc\Title("匿名访问")
 */
#[Prefix('api/v1/guest')]
class GuestController extends Controller
{
    /**
     * @Apidoc\Title("上午、中午、下午")
     * @Apidoc\Tag("匿名访问")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/guest/apm")
     */
    #[Post('apm')]
    public function apm(Request $request)
    {
        $hour = date('H');
        if ($hour < 12) {
            $time = '上午';
        } elseif ($hour < 18) {
            $time = '下午';
        } else {
            $time = '晚上';
        }
        return  $this->success('请求成功', $time);
    }
}
