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
use Illuminate\Support\Facades\Auth as Author;
use App\Models\Order;
use App\Models\OrderRefund;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Http\Middleware\Auth;
use Illuminate\Support\Facades\Log;
use App\Classes\Pay\Weixin;
use DB;

/**
 * @Apidoc\Title("平台-订单退款")
 */
#[Prefix('api/v1/admin/order/refund')]
#[Middleware(Auth::class)]
class OrderRefundController extends \App\Http\Controllers\Controller
{
    /**
     * @Apidoc\Title("申请退款")
     * @Apidoc\Tag("订单退款")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/order/refund/apply")
     * @Apidoc\Query("order_id", type="int",require=true, desc="订单ID")
     * @Apidoc\Query("order_number", type="string",require=true, desc="订单号")
     * @Apidoc\Query("refund_amount", type="float",require=true, desc="退款金额")
     * @Apidoc\Query("refund_reason", type="string",require=false, desc="退款原因")
     */
    #[Post('apply')]
    public function apply(Request $request)
    {
        // 验证请求参数
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
            'order_number' => 'required|string',
            'refund_amount' => 'required|numeric|min:0.01',
            'refund_reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first());
        }

        $order_id = $request->input('order_id');
        $order_number = $request->input('order_number');
        $refund_amount = floatval($request->input('refund_amount'));
        $refund_reason = $request->input('refund_reason', '管理员申请退款');

        // 查询订单信息
        $order = Order::find($order_id);
        if (!$order) {
            return $this->error('订单不存在');
        }

        // 验证订单号是否匹配
        if ($order->order_number !== $order_number) {
            return $this->error('订单号不匹配');
        }

        // 验证订单是否已支付
        if (!$order->paid_at) {
            return $this->error('订单未支付，无法退款');
        }

        // 获取可退款金额
        $real_amount = $order->real_amount;
        
        // 验证可退款金额
        if ($real_amount <= 0) {
            return $this->error('订单无可退款金额');
        }

        // 验证退款金额是否超过可退款金额
        if ($refund_amount > $real_amount) {
            return $this->error("退款金额不能超过可退款金额 {$real_amount}");
        }

        // 开始事务
        DB::beginTransaction();
        try {
            // 生成退款单号
            $refund_number = 'RF' . date('YmdHis') . rand(1000, 9999);
            
            // 创建退款记录
            $refund = new OrderRefund();
            $refund->order_id = $order_id;
            $refund->order_number = $order_number;
            $refund->refund_number = $refund_number;
            $refund->refund_amount = $refund_amount;
            $refund->refund_status = 'pending'; // 退款状态：pending-处理中
            $refund->save();

            // 调用微信退款接口
            $weixin = new Weixin();
            $result = $weixin->refund(
                $refund_number,                // 退款单号
                $order_number,                // 原订单号
                floatval($order->paid_amount), // 订单总金额
                $refund_amount,               // 退款金额
                $refund_reason                // 退款原因
            );

            // 处理退款结果
            if ($result['status'] === 'ok') {
                // 更新退款记录
                $refund->refund_status = 'success'; // 退款状态：success-成功
                $refund->refund_number_platform = $result['refund_id'] ?? '';
                $refund->refunded_at = now();
                $refund->refund_reason = $refund_reason;
                $refund->save();

                // 更新订单退款金额和状态
                $order->refunded_amount = floatval($order->refunded_amount) + $refund_amount;
                
                // 如果退款金额等于支付金额，则将订单状态改为已退款
                if (floatval($order->refunded_amount) >= floatval($order->paid_amount)) {
                    $order->order_status = 'refunded';
                }
                
                $order->save();

                DB::commit();
                return $this->success('退款申请成功');
            } else {
                // 退款失败
                $refund->refund_status = 'failed'; // 退款状态：failed-失败
                $refund->save();
                
                DB::rollBack();
                return $this->error('退款申请失败：' . ($result['message'] ?? '未知错误'));
            }
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('退款异常：' . $e->getMessage());
            return $this->error('退款处理异常：' . $e->getMessage());
        }
    }

    /**
     * @Apidoc\Title("退款记录分页")
     * @Apidoc\Tag("订单退款")
     * @Apidoc\Method ("POST")
     * @Apidoc\Url ("/api/v1/admin/order/refund/page")
     * @Apidoc\Query("order_number", type="string",require=false, desc="订单号")
     * @Apidoc\Query("refund_number", type="string",require=false, desc="退款单号")
     * @Apidoc\Query("refund_status", type="string",require=false, desc="退款状态")
     */
    #[Post('page')]
    public function page(Request $request)
    {
        $order_number = $request->input('order_number', '');
        $refund_number = $request->input('refund_number', '');
        $refund_status = $request->input('refund_status', '');

        $query = OrderRefund::orderBy('id', 'desc');

        if ($order_number) {
            $query->where('order_number', $order_number);
        }

        if ($refund_number) {
            $query->where('refund_number', $refund_number);
        }

        if ($refund_status) {
            $query->where('refund_status', $refund_status);
        }

        $list = $query->paginate($request->input('per_page', 10));

        // 加载订单关联
        foreach ($list->items() as &$refund) {
            $refund->order;
        }

        return $this->success('', $list);
    }

    /**
     * @Apidoc\Title("退款详情")
     * @Apidoc\Tag("订单退款")
     * @Apidoc\Method ("GET")
     * @Apidoc\Url ("/api/v1/admin/order/refund/detail/{id}")
     */
    #[Get('detail/{id}')]
    public function detail($id)
    {
        $refund = OrderRefund::find($id);
        if (!$refund) {
            return $this->error('退款记录不存在');
        }

        // 加载订单关联
        $order = $refund->order;
        if (!$order) {
            return $this->error('关联订单不存在');
        }

        // 显式加载订单项目
        $order->load('orderItems');

        // 返回包含退款信息和订单信息的数据结构
        $data = [
            'refund' => $refund,
            'order' => $order
        ];

        return $this->success('', $data);
    }
}