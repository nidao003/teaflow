<?php

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
 * @Apidoc\Title("平台-优惠券领取记录")
 */
#[Prefix('api/v1/admin/coupon_user')]
#[Middleware(AdminAuth::class)]
class CouponUserController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("优惠券领取记录分页")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/coupon_user/page")
     * @Apidoc\Query("user_id", type="string",require=false, desc="用户ID")
     * @Apidoc\Query("coupon_id", type="string",require=false, desc="优惠券ID")
     * @Apidoc\Query("page", type="string",require=false, desc="页码")
     * @Apidoc\Query("per_page", type="string",require=false, desc="每页条数")
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = CouponUser::orderBy('id', 'desc');
        $model = $model->paginate($per_page);
        $index = ($page - 1) * $per_page + 1 ;
        $model->each(function (&$item) use (&$index) {
            $item->index = $index++;
            $item->user;
            $item->coupon;
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("发放优惠券")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/coupon_user/create")
     * @Apidoc\Query("user_id", type="string",require=true, desc="用户ID")
     * @Apidoc\Query("coupon_id", type="string",require=true, desc="优惠券ID")
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $rules = [
            'user_id' => [
                'required',
            ],
            'coupon_id'  => [
                'required',
            ],
        ];
        $err = $this->validate($request, $rules);
        if ($err) {
            return $err;
        }
        $data = $request->all();
        $data['status'] = 'wait';
        $model = CouponUser::create($data);
        return $this->success("操作成功");
    }
}
