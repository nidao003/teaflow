<?php

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
use App\Exports\OrderExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @Apidoc\Title("平台-导出")
 */
#[Prefix('api/v1/admin/export')]
#[Middleware(AdminAuth::class)]
class ExportController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("导出订单")
     * @Apidoc\Tag("导出")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/export/order")
     * @Apidoc\Query("start_date", type="string",require=true, desc="开始日期")
     * @Apidoc\Query("end_date", type="string",require=true, desc="结束日期")
     */
    #[Post('order')]
    public function order(Request $request)
    {
        $path = 'export/orders-'.date('Y-m-d').'.xlsx';
        $url =  Excel::store(new OrderExport(), 'public/'.$path, 'local');
        return $this->success('请求成功', [
            'url' => asset('storage/'.$path),
        ]);
    }
}
