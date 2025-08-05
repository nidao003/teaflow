<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\AdminAuth as Auth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Setting;

/**
 * @Apidoc\Title("平台-设置")
 */
#[Prefix('api/v1/admin/setting')]
#[Middleware(Auth::class)]
class SettingController extends \App\Http\Controllers\Controller
{
    /**
     * bool类型的字段
     */
    protected $boolTitle = ['isOpen'];
    /**
     * @Apidoc\Title("获取配置")
     * @Apidoc\Tag("配置")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/setting/index")
     * @Apidoc\Query("filed", type="string",require=false, desc="显示的字段，如title,content")
     */
    #[Post('index')]
    public function index(Request $request)
    {
        /**
         * Setting取所有值
         */
        $data = Setting::get()->toArray();
        /**
         * 返回 title=>content
         */
        $data = array_column($data, 'content', 'title');
        if ($request->input('fields')) {
            $fileds = explode(',', $request->input('fields'));
            $new_data = [];
            foreach ($fileds as $key => $value) {
                if (isset($data[$value])) {
                    $new_data[$value] = $data[$value];
                }
            }
            $data = $new_data;
        }
        $data = \App\Classes\Setting::getAllowData($data);
        return $this->success("", $data);
    }
    /**
     * @Apidoc\Title("修改配置")
     * @Apidoc\Tag("配置")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/setting/update")
     * @Apidoc\Param("data",type="json",desc="排序数据",children={
     * })
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $data = $request->input('data');
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                if (in_array($k, $this->boolTitle)) {
                    if ($v == 'true') {
                        $v = 1;
                    } else {
                        $v = 0;
                    }
                }
                Setting::updateOrCreate(['title' => $k], ['content' => $v]);
            }
        }
        return $this->success("");
    }
}
