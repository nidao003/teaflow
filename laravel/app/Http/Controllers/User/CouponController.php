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
use Spatie\RouteAttributes\Attributes\Put;
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\Auth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Coupon;
use App\Models\CouponUser;
use App\Models\CouponOrder;
use App\Models\CouponProduct;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserReg;
use App\Classes\MapTencent;
use Illuminate\Support\Facades\DB;

/**
 * @Apidoc\Title("用户-优惠券")
 */
#[Prefix('api/v1/user/coupon')]
#[Middleware(Auth::class)]
class CouponController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/coupon/page")
     * @Apidoc\Query("status", type="string",require=true, desc="状态 wait expired")
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $status = $request->input('status', 'wait');
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = CouponUser::orderBy('id', 'desc')
                    ->where('user_id', Author::user()->id);
        $query = clone $model;
        $hasExpired = $query->where('status', 'wait')->whereDate('expire_at', '<', date('Y-m-d'))->get();
        if ($hasExpired) {
            foreach ($hasExpired as $key => $value) {
                $value->status = 'expired';
                $value->save();
            }
        }
        $model = $model->where('status',$status);
        $model = $model->paginate($per_page);
        $index = ($page - 1) * $per_page + 1 ;
        $model->each(function (&$item) use (&$index) {
            $item->index = $index++;
            $item->product;
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("可用优惠券数量")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/coupon/count")
     */
    #[Post('count')]
    public function count(Request $request)
    {
        $user_id = Author::user()->id;
        $model = CouponUser::orderBy('id', 'desc')
                    ->where('user_id', $user_id);
        $count = $model->where('status', 'wait')->whereDate('expire_at', '>=', date('Y-m-d'))->count();
        return $this->success("", $count);
    }
    /**
     * @Apidoc\Title("可用优惠券")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/coupon/list")
     * @Apidoc\Query("product_id", type="integer",require=false, desc="商品id")
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $user_id = Author::user()->id;
        $product_id = $request->input('product_id', '');
        if (!is_array($product_id)) {
            $product_id = [$product_id];
        }
        $model = CouponUser::orderBy('id', 'desc')
                    ->where('user_id', $user_id);
        if ($product_id) {
            $model = $model->whereHas('coupon', function ($query) use ($product_id) {
                $query->whereHas('product', function ($query) use ($product_id) {
                    $query->whereIn('product_id', $product_id);
                });
            });
        }
        $all = $model->where('status', 'wait')->whereDate('expire_at', '>=', date('Y-m-d'))->get();
        $list = [];
        if ($all) {
            foreach ($all as $v) {
                $list[] = [
                    'id' => $v->id,
                    'coupon_id' => $v->coupon_id,
                    'coupon' => $v->coupon,
                    'created_at' => $v->created_at,
                    'expire_at' => $v->expire_at,
                    'discount' => $v->coupon->discount,
                    'order_number' => $v->order_number,
                    'get_way' => $v->get_way,
                    'expire_color' => $v->expire_color,
                    'status_color' => $v->status_color,
                    'status_label' => $v->status_label,
                ];
            }
        }
        return $this->success("", [
            'list' => $list,
            'count' => count($list),
        ]);
    }

    /**
     * @Apidoc\Title("自动领取")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/user/coupon/auto_get")
     *
     */
    #[Post('auto_get')]
    public function autoGet(Request $request)
    {
        $list = \App\Classes\Coupon::init(Author::user()->id);
        return $this->success("操作成功", $list);
    }

}
