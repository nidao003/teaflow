<?php

namespace App\Http\Controllers\Front;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\Auth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Setting;

/**
 * @Apidoc\Title("前端-设置")
 */
#[Prefix('api/v1/front/setting')]
class SettingController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("获取搜索关键词")
     * @Apidoc\Tag("配置")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/front/setting/keywords")
     */
    #[Post('keywords')]
    public function keywords(Request $request)
    {
        $data = Setting::where(['title' => 'keywords'])->first();
        $content = $data->content ?? ['美式'];
        return $this->success("", $content);
    }

}
