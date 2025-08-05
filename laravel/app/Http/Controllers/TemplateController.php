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
use App\Models\Template;

/**
 * @Apidoc\Title("通用-小程序模板")
 */
#[Prefix('api/v1/template')]
class TemplateController extends Controller
{
    /**
    * @Apidoc\Title("首页模板")
    * @Apidoc\Tag("小程序模板")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/template/home")
    */
    #[Post('home')]
    public function home(Request $request)
    {
        $data = Template::where(['is_home' => 1])->first();
        $data = $data->data ?? [];
        $banner = [];
        if ($data) {
            foreach ($data as $k => $v) {
                $type = $v['type'];
                if ($type == 'banner') {
                    $banner = $v['data'];
                    unset($data[$k]);
                }
            }
        }
        return $this->success("", [
            'banner' => $banner,
            'data' => $data
        ]);
    }
}
