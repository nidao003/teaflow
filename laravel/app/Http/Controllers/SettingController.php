<?php

namespace App\Http\Controllers;

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
 * @Apidoc\Title("通用-设置")
 */
#[Prefix('api/v1/setting')]
#[Middleware(Auth::class)]
class SettingController extends Controller
{
    /**
     * bool类型的字段
     */
    protected $boolTitle = ['isOpen'];
    /**
     * @Apidoc\Title("获取配置")
     * @Apidoc\Tag("配置")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/setting/index")
     */
    #[Post('index')]
    public function index(Request $request)
    {
        $user = Author::user();
        $type = $user->type;
        $id = 0;
        if (in_array($type, ['seller', 'store'])) {
            if (!$user->seller) {
                return $this->success("", []);
            }
            $id = $this->seller_id;
        }
        /**
         * Setting取所有值
         */
        $data = Setting::where(['seller_id' => $id])->get()->toArray();
        /**
         * 返回 title=>content
         */
        $data = array_column($data, 'content', 'title');
        if (!isset($data['businessHours'])) {
            $data['businessHours'] = [
                'start' => '09:00',
                'end' => '21:00'
            ];
        }
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
        $data['type'] = $type;
        $data['seller'] = $id;
        return $this->success("", $data);
    }
    /**
     * @Apidoc\Title("修改配置")
     * @Apidoc\Tag("配置")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/setting/update")
     * @Apidoc\Query("data", type="string",require=true, desc="数组")
     * @Apidoc\Param("address",type="string",desc="地址")
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $user = Author::user();
        $type = $user->type;
        $id = 0;
        if (in_array($type, ['seller', 'store'])) {
            if (!$user->seller) {
                return $this->success("", []);
            }
            $id = $this->seller_id;
        }
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
                Setting::updateOrCreate(['title' => $k, 'seller_id' => $id], ['content' => $v]);
            }
        }
        return $this->success("");
    }
}
