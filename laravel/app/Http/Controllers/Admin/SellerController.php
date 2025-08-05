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
use App\Models\Seller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserReg;

/**
 * @Apidoc\Title("平台-商家")
 */
#[Prefix('api/v1/admin/seller')]
#[Middleware(AdminAuth::class)]
class SellerController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("列表")
     * @Apidoc\Tag("商家")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/seller/list")
     * @Apidoc\Param("keyword", type="string", desc="搜索关键字")
     * @Apidoc\Param("id", type="integer",  desc="id")
     *
     */
    #[Post('list')]
    public function list(Request $request)
    {
        $limit = 20;
        $keyword = $request->input('keyword');
        $id = $request->input('id');
        if ($id) {
            $item = Seller::where('id', $id)->first();
            $item->label = $item->title." （".$item->phone."）" ;
            return $this->success("", [$item]);
        }
        if ($keyword) {
            $model = Seller::where('title', 'like', '%' . $keyword . '%')->limit($limit)->get();
        } else {
            $model = Seller::orderBy('id', 'desc')->limit($limit)->get();
        }
        $model->each(function ($item) {
            $item->label = $item->title." （".$item->phone."）" ;
        });
        return $this->success("", $model);
    }
    /**
     * @Apidoc\Title("分页")
     * @Apidoc\Tag("商家")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/seller/page")
     *
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = Seller::orderBy('id', 'desc');
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
     * @Apidoc\Url ("/api/v1/admin/seller/create")
     *
     */
    #[Post('create')]
    public function create(Request $request)
    {
        $err = $this->validate($request, [
            'title' => [
                'required',
                Rule::unique('sellers'),
            ],
            'email' => [
                'required',
                Rule::unique('sellers'),
            ],
            'phone' => [
                'required',
                Rule::unique('sellers'),
            ],
        ]);
        if ($err) {
            return $err;
        }
        $title = $request->input('title');
        $email = $request->input('email');
        $phone = $request->input('phone');
        //生成随机密码,复杂的密码
        $password =  User::generateRandomPassword();
        //数据库事务
        DB::beginTransaction();
        try {
            $res = User::regByEmailAndPhone($email, $phone, $password, $type = 'seller');
            if ($res['code'] !== 0) {
                return $res;
            }
            $model = Seller::create([
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
     * @Apidoc\Url ("/api/v1/admin/seller/update")
     *
     */
    #[Post('update')]
    public function update(Request $request)
    {
        $err = $this->validate($request, [
            'id' => 'required|exists:sellers',
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
        Seller::where('id', $id)->update([
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
     * @Apidoc\Url ("/api/v1/admin/seller/delete")
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
        /**
         * 判断有没有门店
         */
        if (Store::where('seller_id', $id)->count() > 0) {
            return $this->error("该商家下有门店,不能删除");
        }
        Seller::where('id', $id)->delete();
        return $this->success("操作成功");
    }
    /**
     * @Apidoc\Title("获取一条记录")
     * @Apidoc\Tag("商家")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/seller/view")
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
        if (!$model = Seller::where(['id' => $id])->first()) {
            return $this->error("数据不存在");
        }
        return $this->success("", $model);
    }
}
