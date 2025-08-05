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
use hg\apidoc\annotation as Apidoc;
use Spatie\RouteAttributes\Attributes\Middleware;
use App\Http\Middleware\AdminAuth;
use Illuminate\Support\Facades\Auth as Author;
use App\Models\User;
use App\Models\UserMoney;

/**
 * @Apidoc\Title("平台-用户充值")
 */
#[Prefix('api/v1/admin/user_money')]
#[Middleware(AdminAuth::class)]
class UserMoneyController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("分页显示")
     * @Apidoc\Tag("用户")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/user_money/page")
     * @Apidoc\Query("title", type="string",require=true, desc="手机号或邮箱")
     * @Apidoc\Query("user_id", type="integer",require=true, desc="用户ID")
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $this->validate($request, [
            'page' => 'integer|min:1',
            'limit' => 'integer|min:1',
        ]);
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 10);
        $model = UserMoney::orderBy('id', 'desc');

        $user = User::orderBy('id', 'desc');
        $title = $request->input('title');
        if ($title) {
            $user = $user->where('phone', 'like', "%$title%")->orWhere('email', 'like', "%$title%");
            //取id
            $user_ids = $user->pluck('id')->toArray();
            $model = $model->whereIn('user_id', $user_ids);
        }

        $model = $model->paginate($per_page);
        $index = ($page - 1) * $per_page + 1 ;
        $model->each(function (&$item) use (&$index) {
            $item->total_amount = $item->getTotalAmount($item->user_id);
            $item->user = $item->user;
            $item->index = $index++;
        });
        return $this->success("", $model);
    }
}
