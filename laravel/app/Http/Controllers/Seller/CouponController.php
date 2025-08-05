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
 * @Apidoc\Title("商家-选择用户优惠券")
 */
#[Prefix('api/v1/seller/coupon')]
#[Middleware(SellerAuth::class)]
class CouponController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("可用优惠券")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/coupon/list")
     * @Apidoc\Query("user_id", type="integer",require=false, desc="用户id")
     * @Apidoc\Query("product_id", type="integer",require=false, desc="商品id")
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $user_id = $request->input('user_id', '');
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
            'user_id' => $user_id,
            'list' => $list,
            'count' => count($list),
        ]);
    }

}
