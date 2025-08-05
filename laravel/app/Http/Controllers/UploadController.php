<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use App\Http\Middleware\Auth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Address;
use App\Classes\Arr;

/**
 * @Apidoc\Title("通用-上传")
 */
#[Prefix('api/v1/upload')]
class UploadController extends Controller
{
    /**
     * @Apidoc\Title("上传图片")
     * @Apidoc\Tag("地址")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/upload/image")
     */
    #[Post('image')]
    public function index(Request $request)
    {
        $file = $request->file('file');
        if (!$file->isValid()) {
            return $this->error('上传文件无效');
        }
        $file_ext = $file->extension();
        $file_size = $file->getSize();
        $file_type = $file->getMimeType();
        if ($file_size > 1024 * 1024 * 10) {
            return $this->error('上传文件大小不能超过10M');
        }
        $file_type_arr = ['image/jpeg', 'image/png', 'image/gif','image/webp','image/jpg'];
        if (!in_array($file_type, $file_type_arr)) {
            return $this->error('上传文件类型不正确');
        }
        //生成随机不重复的文件名
        $rand =  substr(md5(time() . rand(1000, 9999)), 8, 16);
        $file_name = $rand . '.' . $file_ext;
        $path =  date('Y-m').'/'.date('d').'/';
        $file_path = public_path('uploads') . '/' .$path ;
        $file->move($file_path, $file_name);
        $url = '/uploads/' .$path. $file_name;
        /**
         * 带域名
         */
        $url = env('APP_URL').$url;
        return $this->success('上传成功', [
           'url' => $url,
        ]);
    }


}
