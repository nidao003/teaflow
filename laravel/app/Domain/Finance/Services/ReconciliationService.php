<?php

declare(strict_types=1);

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Enums\ReconciliationStatus;
use App\Domain\Finance\Models\ReconciliationDaily;
use App\Domain\Finance\Support\Amount;
use App\Domain\Finance\Support\Period;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 对账服务：按门店 + 日期 + 渠道记录系统金额/渠道金额/差异。
 *
 * - system_amount 默认由 payments（status=success）按渠道聚合，也可显式传入；
 * - diff = channel_amount - system_amount；
 * - |diff| <= threshold → matched，否则 exception；未给 channel_amount → pending。
 */
class ReconciliationService
{
    /** 默认差异阈值（元）。 */
    private const DEFAULT_THRESHOLD = 0.01;

    /**
     * 记录/更新一条渠道对账（同门店同日同渠道唯一，重跑即覆盖）。
     *
     * @param  array{
     *     channel_amount?:float|int|string|null,
     *     system_amount?:float|int|string|null,
     *     threshold?:float|int|string|null
     * }  $data
     */
    public function record(int $storeId, string|\DateTimeInterface $date, string $channel, array $data = []): ReconciliationDaily
    {
        $day = Period::normalizeDate($date);
        $channel = strtolower(trim($channel));

        if ($channel === '') {
            throw new \InvalidArgumentException('缺少对账渠道 channel');
        }

        $systemAmount = array_key_exists('system_amount', $data) && $data['system_amount'] !== null
            ? Amount::round2($data['system_amount'])
            : $this->systemAmount($storeId, $day, $channel);

        $threshold = array_key_exists('threshold', $data) && $data['threshold'] !== null
            ? abs((float) $data['threshold'])
            : self::DEFAULT_THRESHOLD;

        $channelAmountRaw = $data['channel_amount'] ?? null;

        if ($channelAmountRaw === null) {
            $status = ReconciliationStatus::PENDING;
            $channelAmount = 0.0;
            $diff = 0.0;
        } else {
            $channelAmount = Amount::round2($channelAmountRaw);
            $diff = Amount::round2($channelAmount - $systemAmount);
            $status = abs($diff) <= $threshold ? ReconciliationStatus::MATCHED : ReconciliationStatus::EXCEPTION;
        }

        return DB::transaction(fn (): ReconciliationDaily => ReconciliationDaily::query()->updateOrCreate(
            [
                'store_id' => $storeId,
                'date' => $day,
                'channel' => $channel,
            ],
            [
                'system_amount' => $systemAmount,
                'channel_amount' => $channelAmount,
                'diff' => $diff,
                'status' => $status,
            ]
        ));
    }

    /**
     * 系统金额：某门店某日某渠道支付成功金额合计。
     */
    public function systemAmount(int $storeId, string|\DateTimeInterface $date, string $channel): float
    {
        $day = Period::normalizeDate($date);
        [$start, $end] = Period::bounds($day);

        return Amount::round2(
            DB::table('payments as p')
                ->join('orders as o', 'o.order_number', '=', 'p.order_number')
                ->where('o.store_id', $storeId)
                ->where('p.channel', strtolower(trim($channel)))
                ->where('p.status', 'success')
                ->whereBetween('p.paid_at', [$start, $end])
                ->sum('p.amount')
        );
    }

    /**
     * 查询某门店某日的全部对账记录。
     *
     * @return Collection<int, ReconciliationDaily>
     */
    public function listForDay(int $storeId, string|\DateTimeInterface $date): Collection
    {
        return ReconciliationDaily::query()
            ->where('store_id', $storeId)
            ->where('date', Period::normalizeDate($date))
            ->orderBy('channel')
            ->get();
    }
}
