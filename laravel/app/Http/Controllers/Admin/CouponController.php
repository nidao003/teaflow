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
 * @Apidoc\Title("平台-优惠券")
 */
#[Prefix('api/v1/admin/coupon')]
#[Middleware(AdminAuth::class)]
class CouponController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/coupon/page")
     *
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = Coupon::orderBy('id', 'desc');
        $model = $model->paginate($per_page);
        $index = ($page - 1) * $per_page + 1 ;
        $model->each(function (&$item) use (&$index) {
            $item->index = $index++;
            $item->product;
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("创建")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/coupon/create")
     *
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $err = $this->commValidate($request);
        if ($err) {
            return $err;
        }
        $data = $request->all();
        DB::beginTransaction();
        try {
            $data['source'] = 'local';
            $model = Coupon::create($data);
            $coupon_id = $model->id;
            $products = $data['products'];
            foreach ($products as $product) {
                CouponProduct::create([
                    'coupon_id' => $coupon_id,
                    'product_id' => $product,
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("修改")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/coupon/update")
     *
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $err = $this->commValidate($request);
        if ($err) {
            return $err;
        }
        $coupon_id = $request->input('id');
        if (!$model = Coupon::where(['id' => $coupon_id])->first()) {
            return $this->error("数据不存在");
        }
        $data = $request->all();
        if (isset($data['id'])) {
            unset($data['id']);
        }
        $data = $request->all();

        \DB::transaction(function () use ($coupon_id, $data) {
            Coupon::updateWithFillable($coupon_id, $data);
            $products = $data['products'];
            CouponProduct::where('coupon_id', $coupon_id)->delete();
            foreach ($products as $product) {
                CouponProduct::create([
                    'coupon_id' => $coupon_id,
                    'product_id' => $product,
                ]);
            }
        });


        return $this->success("操作成功");
    }
    protected function commValidate($request)
    {
        $rules = [
            'title' => [
                'required',
            ],
            'type'  => [
                'required',
            ],
            'discount' => [
                'required',
            ],
            'days' => [
                'required',
            ],
        ];
        if ($request->input('id')) {
            $rules['id'] = 'required|exists:coupon';
        }
        $err = $this->validate($request, $rules);
        if ($err) {
            return $err;
        }
        $products = $request->input('products');
        if (!$products || !is_array($products)) {
            return '请选择商品';
        }
    }
    /**
     * @Apidoc\Title("删除")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/coupon/delete")
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
        Coupon::where('id', $id)->delete();
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("获取一条记录")
     * @Apidoc\Tag("优惠券")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/coupon/view")
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
        if (!$model = Coupon::where(['id' => $id])->first()) {
            return $this->error("数据不存在");
        }
        $product = $model->product;
        $products = [];
        foreach ($product as $k => $v) {
            $products[] = $v->product_id;
        }
        $model->products = $products;
        return $this->success("", $model);
    }
}
