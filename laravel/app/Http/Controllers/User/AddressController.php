<?php
/** 
 * @author sunkangchina <68103403@qq.com>
 * @license MIT <https://mit-license.org/> 
 */
namespace App\Http\Controllers\User;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use Illuminate\Support\Facades\Auth as Author;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Middleware\Auth;
use Illuminate\Support\Facades\Log;
use DB;
use App\Models\UserAddress;
Use App\Classes\MapTiandi;

/**
 * @Apidoc\Title("用户-收货地址")
 */
#[Prefix('api/v1/user/address')]
#[Middleware(Auth::class)]
class AddressController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("取默认收货地址")
     * @Apidoc\Tag("收货地址")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/address/default")
     */
    #[Post('default')]
    public function default(Request $request)
    {
        $user_id = Author::user()->id;
        $model = UserAddress::where('user_id', $user_id)
                    ->where('is_default', 1);
        $all = $model->first();
        if(!$all){
            return $this->error("");
        }
        return $this->success("", $all);
    }
    /**
     * @Apidoc\Title("查看")
     * @Apidoc\Tag("收货地址")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/address/view")
     * @Apidoc\Param ("id", "int", "required", "")
     */
    #[Post('view')]
    public function view(Request $request)
    {
        $user_id = Author::user()->id;
        $id = $request->get('id');
        if(!$id){
            return $this->error("id不能为空");
        }
        $model = UserAddress::where('user_id', $user_id)
                    ->where('id', $id)->first();
        if(!$model){
            return $this->error("");
        }
        return $this->success("", $model);
    }

    /**
     * @Apidoc\Title("删除")
     * @Apidoc\Tag("收货地址")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/address/delete")
     * @Apidoc\Param ("id", "int", "required", "")
     */
    #[Post('delete')]
    public function delete(Request $request)
    {
        $user_id = Author::user()->id;
        $id = $request->get('id');
        if(!$id){
            return $this->error("id不能为空");
        }
        $model = UserAddress::where('user_id', $user_id)
                    ->where('id', $id)->first();
        if(!$model){
            return $this->error("");
        }
        $model->delete();
        return $this->success("");
    }

    /**
     * @Apidoc\Title("数量")
     * @Apidoc\Tag("收货地址")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/address/count")
     */
    #[Post('count')]
    public function count(Request $request)
    {
        $user_id = Author::user()->id;
        $model = UserAddress::where('user_id', $user_id);
        $all = $model->count();
        return $this->success("", $all);
    }


    /**
     * @Apidoc\Title("列表")
     * @Apidoc\Tag("收货地址")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/address/list")
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $user_id = Author::user()->id;
        $model = UserAddress::orderBy('is_default', 'desc')->orderBy('id', 'desc')
                    ->where('user_id', $user_id);
        $all = $model->limit(50)->get();
        return $this->success("", $all);
    }

    /**
     * @Apidoc\Title("添加")
     * @Apidoc\Tag("收货地址")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/address/add")
     */
    #[Post('add')]
    public function add(Request $request)
    {
        $user_id = Author::user()->id;
        $model = new UserAddress();
        $model->user_id = $user_id;
        $model->title = $request->get('title');
        $model->phone = $request->get('phone'); 
        $model->address = $request->get('address'); 
        $this->commonModel($request, $model); 
        $err = $this->validate($request, [
            'title' => 'required',
            'phone' => 'required',
            'address' => 'required',
            'lat' => 'required',
            'lng' => 'required',
            'province' => 'required',
            'city' => 'required',
            'district' => 'required',
        ]);
        if ($err) {
            return $err;
        }
        $map = MapTiandi::get($model->province.$model->city.$model->district.$request->get('address')); 
        $lat = $map['lat'] ?? "";
        $lng = $map['lng'] ?? "";
        if ( !$lat || !$lng) { 
            return $this->error('收货地址不能为空');
        }
        $model->lat = $lat;
        $model->lng = $lng;
        $model->save();
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("编辑")
     * @Apidoc\Tag("收货地址")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/address/edit")
     */
    #[Post('edit')]
    public function edit(Request $request)
    {
        $user_id = Author::user()->id;
        $model = UserAddress::find($request->get('id'));
        if (!$model || $model->user_id != $user_id) {
            return $this->error('');
        }
        $model->title = $request->get('title'); 
        $model->phone = $request->get('phone');  
        $this->commonModel($request, $model);
        $err = $this->validate($request, [
            'title' => 'required',
            'phone' => 'required',
            'address' => 'required',
            'lat' => 'required',
            'lng' => 'required',
            'province' => 'required',
            'city' => 'required',
            'district' => 'required',
        ]);
        if ($err) {
            return $err;
        }
        $model->address = $request->get('address');
        $map = MapTiandi::get($model->province.$model->city.$model->district.$request->get('address'));
        $lat = $map['lat'] ?? "";
        $lng = $map['lng'] ?? "";
        if (!$lat || !$lng) { 
            return $this->error('收货地址不能为空');
        }
        $model->lat = $lat;
        $model->lng = $lng;
        $model->save();
        return $this->success("", $model);
    }
 
    protected function commonModel($request, &$model)
    {
        $user_id = Author::user()->id;
        if ($request->get('is_default') == 1) {
            UserAddress::where('user_id', $user_id)->update(['is_default' => 0]);
        }
        $model->is_default = $request->get('is_default', 0); 
        $select = $request->get('select', '');
        if($select && is_array($select)){
            $a = $select[0];
            $b = $select[1];
            $c = $select[2];
            $model->province = $a;
            $model->city = $b;
            $model->district = $c;
            $request->merge([
                'province' => $a,
                'city' => $b,
                'district' => $c,
            ]);
        }
    }


}
