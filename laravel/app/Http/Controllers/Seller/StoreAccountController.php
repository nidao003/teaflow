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
use App\Models\Seller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserReg;
use App\Models\StoreAccount;

/**
 * @Apidoc\Title("商家-门店帐号")
 */
#[Prefix('api/v1/seller/store_account')]
#[Middleware(SellerAuth::class)]
class StoreAccountController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("商家")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store_account/page")
     * @Apidoc\Query("store_id", type="string", require=true, desc="门店id")
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $store_id = $request->input('store_id');
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = StoreAccount::orderBy('id', 'desc')->where('store_id', $store_id);
        $model = $model->paginate($per_page);
        $index = ($page - 1) * $per_page + 1 ;
        $model->each(function ($item) use (&$index) {
            $item->index = $index++;
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("创建")
     * @Apidoc\Tag("商家")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store_account/create")
     * @Apidoc\Query("title", type="string", require=true, desc="")
     * @Apidoc\Query("email", type="string", require=true, desc="")
     * @Apidoc\Query("phone", type="string", require=true, desc="")
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $err = $this->validate($request, [
            'title' => [
                'required',
                Rule::unique('store_account'),
            ],
            'email' => [
                'required',
                Rule::unique('store_account'),
            ],
            'phone' => [
                'required',
                Rule::unique('store_account'),
            ],
        ]);
        if ($err) {
            return $err;
        }
        $store_id = $request->input('store_id', 0);
        $title = $request->input('title');
        $email = $request->input('email');
        $phone = $request->input('phone');
        if (!$store_id) {
            return $this->error("请选择门店");
        }
        if (!\App\Classes\Seller::isAllowStoreId($store_id)) {
            return $this->error("没有权限");
        }
        //生成随机密码,复杂的密码
        $password =  User::generateRandomPassword();
        //数据库事务
        DB::beginTransaction();
        try {
            $res = User::regByEmailAndPhone($email, $phone, $password, $type = 'seller');
            if ($res['code'] !== 0) {
                return $res;
            }
            $model = StoreAccount::create([
                'store_id' => $store_id,
                'title' => $title,
                'email' => $email,
                'phone' => $phone,
                'user_id' => $res['data']['user_id'],
            ]);
            /**
             * 发送邮件
             */
            $data = [
                'title' => $title,
                'email' => $email,
                'phone' => $phone,
                'password' => $password,
            ];
            Mail::to($email)->later(now()->addMinutes(1), new UserReg($data));
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage());
        }
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("修改")
     * @Apidoc\Tag("商家")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store_account/update")
     * @Apidoc\Query("id", type="string", require=true, desc="")
     * @Apidoc\Query("title", type="string", require=true, desc="")
     * @Apidoc\Query("email", type="string", require=true, desc="")
     * @Apidoc\Query("phone", type="string", require=true, desc="")
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required|exists:store_account',
            'title' => [
                'required',
                Rule::unique('sellers')->ignore($request->input('id')),
            ],
            'email' => [
                'required',
                Rule::unique('sellers')->ignore($request->input('id')),
            ],
            'phone' => [
                'required',
                Rule::unique('sellers')->ignore($request->input('id')),
            ],
        ]);
        if ($err) {
            return $err;
        }
        $id = $request->input('id');
        $title = $request->input('title');
        $email = $request->input('email');
        $phone = $request->input('phone');
        $store = StoreAccount::where('id', $id)->first();
        $store_id = $store->store_id;
        if (!$store_id) {
            return $this->error("请选择门店");
        }
        if (!\App\Classes\Seller::isAllowStoreId($store_id)) {
            return $this->error("没有权限");
        }

        StoreAccount::where('id', $id)->update([
            'title' => $title,
            'email' => $email,
            'phone' => $phone,
        ]);
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("删除")
     * @Apidoc\Tag("商家")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store_account/delete")
     * @Apidoc\Query("id", type="string", require=true, desc="")
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

        $store = StoreAccount::where('id', $id)->first();
        $store_id = $store->store_id;
        if (!$store_id) {
            return $this->error("请选择门店");
        }
        if (!\App\Classes\Seller::isAllowStoreId($store_id)) {
            return $this->error("没有权限");
        }
        StoreAccount::where('id', $id)->delete();
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("获取一条记录")
     * @Apidoc\Tag("商家")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/seller/store_account/view")
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
        if (!$model = StoreAccount::where(['id' => $id])->first()) {
            return $this->error("数据不存在");
        }
        return $this->success("", $model);
    }
}
