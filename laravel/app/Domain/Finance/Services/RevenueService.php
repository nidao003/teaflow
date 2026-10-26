<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\RevenueDaily;
use App\Domain\Finance\Support\Amount;
use App\Domain\Finance\Support\Period;
use Illuminate\Support\Facades\DB;

/**
 * 收入服务：从 orders / payments / refunds 聚合营业收入日汇总。
 *
 * 口径（经营口径自建，非会计记账）：
 * - gross_amount：支付成功（payments.status=success）的支付金额，按下单门店 + 支付成功日归属；
 * - refund_amount：退款成功（refunds.status=success）的退款金额，按退款成功日归属（非订单日）；
 * - net_amount = gross_amount - refund_amount；
 * - discount_amount = Σ max(0, orders.total_amount - orders.paid_amount)，按下单门店 + 支付成功日归属；
 * - order_count：按下单日（orders.created_at）统计，排除已取消订单；
 * - payment_fee：可显式传 payment_fee，或传 fee_rate 按支付金额比例计算，缺省 0。
 */
class RevenueService
{
    /**
     * 聚合某门店某营业日的收入（只读，不落库）。
     *
     * @param  array{payment_fee?:float|int|string|null, fee_rate?:float|int|string|null}  $options
     * @return array{
     *     store_id:int, date:string, order_count:int, gross_amount:float, discount_amount:float,
     *     refund_amount:float, net_amount:float, payment_fee:float, has_data:bool
     * }
     */
    public function aggregate(int $storeId, string|\DateTimeInterface $date, array $options = []): array
    {
        $day = Period::normalizeDate($date);
        [$start, $end] = Period::bounds($day);

        $grossAmount = Amount::round2(
            DB::table('payments as p')
                ->join('orders as o', 'o.order_number', '=', 'p.order_number')
                ->where('o.store_id', $storeId)
                ->where('p.status', 'success')
                ->whereBetween('p.paid_at', [$start, $end])
                ->sum('p.amount')
        );

        $orderCount = (int) DB::table('orders')
            ->where('store_id', $storeId)
            ->whereBetween('created_at', [$start, $end])
            ->where(function ($query): void {
                $query->whereNull('order_status')->orWhere('order_status', '!=', 'cancelled');
            })
            ->count();

        $discountAmount = Amount::round2(
            DB::table('orders')
                ->where('store_id', $storeId)
                ->whereBetween('paid_at', [$start, $end])
                ->selectRaw('COALESCE(SUM(CASE WHEN total_amount > paid_amount THEN total_amount - paid_amount ELSE 0 END), 0) AS discount')
                ->value('discount')
        );

        $refundAmount = Amount::round2(
            DB::table('refunds as r')
                ->join('orders as o', 'o.order_number', '=', 'r.order_number')
                ->where('o.store_id', $storeId)
                ->where('r.status', 'success')
                ->whereBetween('r.refunded_at', [$start, $end])
                ->sum('r.amount')
        );

        $paymentFee = $this->paymentFee($grossAmount, $options);
        $netAmount = Amount::round2($grossAmount - $refundAmount);

        return [
            'store_id' => $storeId,
            'date' => $day,
            'order_count' => $orderCount,
            'gross_amount' => $grossAmount,
            'discount_amount' => $discountAmount,
            'refund_amount' => $refundAmount,
            'net_amount' => $netAmount,
            'payment_fee' => $paymentFee,
            'has_data' => $orderCount > 0 || $grossAmount !== 0.0 || $refundAmount !== 0.0,
        ];
    }

    /**
     * 重算并落库（幂等：同门店同日 updateOrCreate）；无数据返回 null，不硬造零记录。
     *
     * @param  array{payment_fee?:float|int|string|null, fee_rate?:float|int|string|null}  $options
     */
    public function recalc(int $storeId, string|\DateTimeInterface $date, array $options = []): ?RevenueDaily
    {
        $aggregate = $this->aggregate($storeId, $date, $options);

        if (! $aggregate['has_data']) {
            return null;
        }

        return DB::transaction(fn (): RevenueDaily => RevenueDaily::query()->updateOrCreate(
            [
                'store_id' => $aggregate['store_id'],
                'date' => $aggregate['date'],
            ],
            [
                'order_count' => $aggregate['order_count'],
                'gross_amount' => $aggregate['gross_amount'],
                'discount_amount' => $aggregate['discount_amount'],
                'refund_amount' => $aggregate['refund_amount'],
                'net_amount' => $aggregate['net_amount'],
                'payment_fee' => $aggregate['payment_fee'],
            ]
        ));
    }

    /**
     * @param  array{payment_fee?:float|int|string|null, fee_rate?:float|int|string|null}  $options
     */
    private function paymentFee(float $grossAmount, array $options): float
    {
        $feeRate = $options['fee_rate'] ?? null;

        if ($feeRate !== null) {
            return Amount::round2($grossAmount * (float) $feeRate);
        }

        return Amount::round2($options['payment_fee'] ?? 0);
    }
}
