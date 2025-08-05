<?php

namespace App\Http\Controllers\Front;

use Illuminate\Http\Request;
use Spatie\RouteAttributes\Attributes\Get;
use Spatie\RouteAttributes\Attributes\Prefix;
use Spatie\RouteAttributes\Attributes\Post;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Store;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Classes\Distance;
use App\Classes\StoreSetting;

/**
 * @Apidoc\Title("前端-门店")
 */
#[Prefix('api/v1/front/store')]
class StoreController extends \App\Http\Controllers\Controller
{
    /**
    * @Apidoc\Title("列表")
    * @Apidoc\Tag("门店")
    * @Apidoc\Method ("POST")
    * @Apidoc\Url ("/api/v1/front/store/list")
    * @Apidoc\Query("lat", type="string",require=false, desc="")
    * @Apidoc\Query("lng", type="string",require=false, desc="")
    */
    #[Post('list')]
    public function list(Request $request)
    {
        $lat = $request->input('lat') ?: '31.182021';
        $lng = $request->input('lng') ?: '121.425562';
        $model = Store::where('is_open', 1)
            ->selectRaw("*, ST_Distance(
                ST_GeomFromText('POINT($lng $lat)'),
                ST_GeomFromText(CONCAT('POINT(', stores.lng, ' ', stores.lat, ')'))
            ) as distance")
            ->orderByRaw('distance asc')
            ->get();
        $model->each(function (&$item) use ($lat, $lng) {
            $distance = Distance::get($lng, $lat, $item->lng, $item->lat);
            $item->setAttribute('distance', $distance);
            $distance_str = Distance::getStr($distance);
            $item->setAttribute('distance_str', $distance_str);
        });
        $has_store = 0;
        if ($model) {
            $has_store = 1;
        }
        return $this->success("", ['data' => $model,'has_store' => $has_store]);
    }
    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/front/store/page")
     * @Apidoc\Query("lat", type="string",require=false, desc="")
     * @Apidoc\Query("lng", type="string",require=false, desc="")
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $lat = $request->input('lat') ?: '31.182021';
        $lng = $request->input('lng') ?: '121.425562';
        $model = Store::where('is_open', 1)
            ->selectRaw("*, ST_Distance(
                ST_GeomFromText('POINT($lng $lat)'),
                ST_GeomFromText(CONCAT('POINT(', stores.lng, ' ', stores.lat, ')'))
            ) as distance")
            ->orderByRaw('distance asc');
        $model = $model->paginate($per_page);
        $index = ($page - 1) * $per_page + 1 ;
        $model->each(function (&$item) use (&$index, $lat, $lng) {
            $distance = Distance::get($lng, $lat, $item->lng, $item->lat);
            $item->setAttribute('distance', $distance);
            $distance_str = Distance::getStr($distance);
            $item->setAttribute('distance_str', $distance_str);
            $item->index = $index++;
            $item->user;
            $item->coupon;
            $item->order;
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("显示")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/front/store/view")
     * @Apidoc\Query("id", type="string",require=false, desc="")
     */
    #[Post('view')]
    public function view(Request $request)
    {
        $id = $request->input('id');
        if (!$id) {
            return $this->error("参数错误");
        }
        $model = Store::where(['id' => $id])->first();
        if (!$model) {
            return $this->error("数据不存在");
        }
        $lat = $request->input('lat', '31.182021');
        $lng = $request->input('lng', '121.425562');
        $distance = Distance::get($lng, $lat, $model->lng, $model->lat);
        $model->setAttribute('distance', $distance);
        $distance_str = Distance::getStr($distance);
        $model->setAttribute('distance_str', $distance_str);
        return $this->success("", $model);
    }

    /**
     * @Apidoc\Title("获取配置")
     * @Apidoc\Tag("门店")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/front/store/config")
     * @Apidoc\Query("selected_store_id", type="string",require=false, desc="")
     */
    #[Post('config')]
    public function config(Request $request)
    {
        $store_id = $request->input('selected_store_id');
        if (!$store_id) {
            return $this->error("参数错误");
        } 
        $model = Store::where(['id' => $store_id])->first();
        if (!$model) {
            return $this->error("数据不存在");
        }
        $data['is_open'] = $model->is_open;
        $data['is_delivery'] = $model->is_delivery;
        $data['open_at'] = $model->open_at;
        $data['close_at'] = $model->close_at;
        return $this->success("", $data);
    }

}
