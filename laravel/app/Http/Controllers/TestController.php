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
 * @Apidoc\Title("测试")
 */
#[Prefix('api/v1/test')]
class TestController extends Controller
{
    /**
    * @Apidoc\Title("广播事件")
    * @Apidoc\Tag("测试")
    * @Apidoc\Method ("Post")
    * @Apidoc\Url ("/api/v1/test/event")
    *
    */
    #[Post('event')]
    public function event(Request $request)
    {
        broadcast(new \App\Events\AdminUpdateProductEvent('更新了商品数据'));
        broadcast(new \App\Events\StoreOrderEvent(1));
        broadcast(new \App\Events\StoreOrderCountEvent(1));

        return $this->success('请求成功', [
            'time' => date('Y-m-d H:i:s'),
            'file' => 'TestController',
        ]);
    }

}
