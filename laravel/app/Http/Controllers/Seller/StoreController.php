<?php

namespace App\Http\Controllers\Seller;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use Spatie\RouteAttributes\Attributes\Put;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\SellerAuth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Product;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Store;

/**
 * @Apidoc\Title("商家-门店信息")
 */
#[Prefix('api/v1/seller/store')]
#[Middleware(SellerAuth::class)]
class StoreController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("获取营业时间、配置范围")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store/get_setting")
     */
    #[Post('get_setting')]
    public function get_setting(Request $request)
    {
        $store_id = $this->store_id;

        $store = Store::idCache($store_id);
        $area_range = $store->area_range;
        // area_range转成数字
        if ($area_range) {
            $area_range = floatval($area_range);
        }
        return $this->success("", [
            'is_open' => (int)$store->is_open,
            'is_delivery' => (string)$store->is_delivery,
            'open_at' => $store->open_at,
            'close_at' => $store->close_at,
            'tea_cost_time' => $store->tea_cost_time,
            'area_range' => $area_range,
            'store_id' => $store_id,
        ]);
    }
    /**
     * @Apidoc\Title("更新营业时间、配置范围")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store/update_setting")
     * @Apidoc\Query("is_delivery", type="string",require=false, desc="是否支持配送 ,1支持，0不支持")
     * @Apidoc\Query("is_open", type="string",require=false, desc="是否营业 ,1营业，0不营业")
     * @Apidoc\Query("open_at", type="string",require=false, desc="营业时间")
     * @Apidoc\Query("close_at", type="string",require=false, desc="闭店时间")
     * @Apidoc\Query("area_range", type="string",require=false, desc="配送范围")
     * @Apidoc\Query("tea_cost_time", type="string",require=false, desc="制作时间")
     */
    #[Post('update_setting')]
    public function update_setting(Request $request)
    {
        $is_open = $request->input('is_open');
        $is_delivery = $request->input('is_delivery');
        $open_at = $request->input('open_at', '');
        $close_at = $request->input('close_at', '');
        $area_range = $request->input('area_range', '');
        $tea_cost_time = $request->input('tea_cost_time', '');
         
        $store_id = $this->store_id;
        $store = Store::where('id', $store_id)->first();
        $need_update = false;
        if ($is_open) {
            $store->is_open = $is_open;
            $need_update = true;
        }
        if ($is_delivery) {
            $store->is_delivery = $is_delivery;
            $need_update = true;
        }
        if ($open_at) {
            $store->open_at = $open_at;
            $need_update = true;
        }
        if ($close_at) {
            $store->close_at = $close_at;
            $need_update = true;
        }
        if ($area_range) {
            $store->area_range = $area_range;
            $need_update = true;
        }
        if ($tea_cost_time) {
            $store->tea_cost_time = $tea_cost_time;
            $need_update = true;
        }
        if (!$need_update) {
            return $this->success("更新失败");
        }
        $store->save();
        return $this->success("更新成功", $store_id);
    }

    /**
     * @Apidoc\Title("信息")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store/info")
     *
     */
    #[Post('info')]
    public function info(Request $request)
    {
        $store_id =  $this->store_id;
        $store = Store::where('id', $store_id)->first();
        return $this->success("", $store);
    }

    /**
     * @Apidoc\Title("商家拥有的门店列表")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store/list")
     *
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $seller_id = $this->seller_id;
        $query = Store::where('seller_id', $seller_id);
        $total = $query->count();
        $list = $query->orderBy('id', 'desc')->get();
        return $this->success("", [
            'total' => $total,
            'list' => $list
        ]);
    }

    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store/page")
     *
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $seller_id = $this->seller_id;
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = Store::orderBy('id', 'desc')->where('seller_id', $seller_id);
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
     * @Apidoc\Url ("/api/v1/seller/store/create")
     *
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $err = $this->validate($request, [
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
        $seller_id = $this->seller_id;
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
     * @Apidoc\Url ("/api/v1/seller/store/update")
     *
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required|exists:stores',
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
        $title = $request->input('title');
        $phone = $request->input('phone');
        $area = $request->input('area');
        $address = $request->input('address');
        if (!$model = Store::where(['id' => $id])->first()) {
            return $this->error("数据不存在");
        }
        $seller_id = $this->seller_id;
        if ($seller_id != $model->seller_id) {
            return $this->error("门店不属于当前商家");
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
     * @Apidoc\Url ("/api/v1/seller/store/delete")
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
        $first = Store::where('id', $id)->where('seller_id', $this->seller_id)->first();
        if (!$first) {
            return $this->error("数据不存在");
        }
        Store::where('id', $id)->delete();
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("获取一条记录")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store/view")
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
        if (!$model = Store::where(['id' => $id,'seller_id' => $this->seller_id])->first()) {
            return $this->error("数据不存在");
        }
        return $this->success("", $model);
    }

}
