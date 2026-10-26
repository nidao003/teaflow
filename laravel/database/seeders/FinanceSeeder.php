<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Finance\Services\DailyCloseService;
use App\Domain\Finance\Services\ReconciliationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 财务/经营分析域最小演示数据：门店 1 近两日的订单/支付/退款各一条，然后跑日结 + 渠道对账。
 * 幂等：订单号/支付单号/退款单号固定，重复执行 updateOrInsert 后重算日汇总。
 */
class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $storeId = 1;
        $days = [now()->subDay()->toDateString(), now()->toDateString()];

        foreach ($days as $index => $day) {
            $suffix = str_replace('-', '', $day);
            $orderNumber = 'SEED-FIN-'.$suffix;
            $paidAt = $day.' 12:00:00';

            DB::table('orders')->updateOrInsert(
                ['order_number' => $orderNumber],
                [
                    'get_code' => 'S'.$index,
                    'seller_id' => 1,
                    'store_id' => $storeId,
                    'user_id' => 1,
                    'delivery_type' => 'self-pickup',
                    'order_status' => 'finished',
                    'pay_status' => 'paid',
                    'delivery_status' => 'finished',
                    'payment_method' => 'wechat',
                    'delivery_method' => 'pickup',
                    'paid_at' => $paidAt,
                    'total_quantity' => 2,
                    'total_amount' => 100.00,
                    'paid_amount' => 95.00,
                    'real_amount' => 95.00,
                    'refunded_amount' => 5.00,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]
            );

            DB::table('payments')->updateOrInsert(
                ['out_trade_no' => 'SEED-PAY-'.$suffix],
                [
                    'order_number' => $orderNumber,
                    'store_id' => $storeId,
                    'channel' => 'wechat',
                    'amount' => 95.00,
                    'status' => 'success',
                    'paid_at' => $paidAt,
                    'idempotency_key' => 'seed-fin-'.$day,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]
            );

            DB::table('refunds')->updateOrInsert(
                ['refund_no' => 'SEED-REF-'.$suffix],
                [
                    'order_number' => $orderNumber,
                    'amount' => 5.00,
                    'reason' => 'seed',
                    'status' => 'success',
                    'refunded_at' => $paidAt,
                    'created_at' => $paidAt,
                    'updated_at' => $paidAt,
                ]
            );
        }

        /** @var DailyCloseService $dailyClose */
        $dailyClose = app(DailyCloseService::class);

        $dailyClose->closeRange($storeId, $days[0], $days[1], [
            'labor_cost' => 120.00,
            'other_cost' => 30.00,
            'payment_fee' => 0.95,
        ]);

        /** @var ReconciliationService $reconciliation */
        $reconciliation = app(ReconciliationService::class);

        foreach ($days as $day) {
            $reconciliation->record($storeId, $day, 'wechat', [
                'channel_amount' => 95.00,
                'threshold' => 0.50,
            ]);
        }
    }
}
