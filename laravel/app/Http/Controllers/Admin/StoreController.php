<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Put;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\AdminAuth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Store;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserReg;
use App\Classes\MapTencent;

/**
 * @Apidoc\Title("平台-门店")
 */
#[Prefix('api/v1/admin/store')]
#[Middleware(AdminAuth::class)]
class StoreController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/store/page")
     *
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = Store::orderBy('id', 'desc');
        $model = $model->paginate($per_page);
        $index = ($page - 1) * $per_page + 1 ;
        $model->each(function ($item) use (&$index) {
            $item->seller_label = $item->seller_label;
            $item->index = $index++;
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("创建")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/store/create")
     *
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $err = $this->validate($request, [
            'seller_id' => [
                'required',
            ],
            'title' => [
                'required',
                Rule::unique('stores'),
            ],
            'phone' => [
                'required',
            ],
            'area' => [
                'required',
            ],
            'address' => [
                'required',
            ],

        ]);
        if ($err) {
            return $err;
        }
        $seller_id = $request->input('seller_id');
        $title = $request->input('title');
        $phone = $request->input('phone');
        $area = $request->input('area');
        $address = $request->input('address');
        $full_address = implode('', $area). $address;
        $res = MapTencent::get($full_address);
        $lat = $res['lat'] ?? '';
        $lng = $res['lng'] ?? '';
        $model = Store::create([
            'title' => $title,
            'seller_id' => $seller_id,
            'phone' => $phone,
            'area' => $area,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
        ]);
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("修改")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/store/update")
     *
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required|exists:stores',
            'seller_id' => [
                'required',
            ],
            'title' => [
                'required',
                Rule::unique('stores')->ignore($request->input('id')),
            ],
            'phone' => [
                'required',
            ],
            'area' => [
                'required',
            ],
            'address' => [
                'required',
            ],
        ]);
        if ($err) {
            return $err;
        }
        $id = $request->input('id');
        $seller_id = $request->input('seller_id');
        $title = $request->input('title');
        $phone = $request->input('phone');
        $area = $request->input('area');
        $address = $request->input('address');
        if (!$model = Store::where(['id' => $id])->first()) {
            return $this->error("数据不存在");
        }
        $full_address = implode('', $area). $address;
        $res = MapTencent::get($full_address);
        $lat = $res['lat'] ?? '';
        $lng = $res['lng'] ?? '';

        Store::where('id', $id)->update([
            'seller_id' => $seller_id,
            'title' => $title,
            'phone' => $phone,
            'area' => $area,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
        ]);
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("删除")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/store/delete")
     *
     */
    #[Post('delete')]
    public function delete(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required',
        ]);
        if ($err) {
            return $err;
        }
        $id = $request->input('id');
        Store::where('id', $id)->delete();
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("获取一条记录")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/store/view")
     * @Apidoc\Query("id", type="string",require=true, desc="id")
     */
    #[Post('view')]
    public function view(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required',
        ]);
        if ($err) {
            return $err;
        }
        $id = $request->input('id');
        if (!$model = Store::where(['id' => $id])->first()) {
            return $this->error("数据不存在");
        }
        return $this->success("", $model);
    }
}
