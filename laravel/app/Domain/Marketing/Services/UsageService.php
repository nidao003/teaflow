<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Services;

use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\CampaignUsage;
use App\Domain\Marketing\Support\Money;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

/**
 * 活动使用记录服务：负责落使用记录与幂等去重。
 *
 * 幂等键：unique(campaign_id, member_id, order_number)；
 * order_number 为空时无法幂等去重（由业务方保证只落一次）。
 */
class UsageService
{
    public function record(
        int $campaignId,
        int $memberId,
        ?string $orderNumber,
        int|float|string $amount,
        ?DateTimeInterface $usedAt = null
    ): CampaignUsage {
        if ($memberId <= 0) {
            throw new InvalidArgumentException('member_id 必须为正整数');
        }

        Campaign::query()->findOrFail($campaignId);

        $amountCents = Money::toCents($amount);
        if ($amountCents < 0) {
            throw new InvalidArgumentException('使用金额不能为负数');
        }

        $orderNumber = $orderNumber === null ? null : trim($orderNumber);
        if ($orderNumber === '') {
            $orderNumber = null;
        }

        if ($orderNumber !== null) {
            $existing = CampaignUsage::query()
                ->where('campaign_id', $campaignId)
                ->where('member_id', $memberId)
                ->where('order_number', $orderNumber)
                ->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        try {
            return CampaignUsage::query()->create([
                'campaign_id' => $campaignId,
                'member_id' => $memberId,
                'order_number' => $orderNumber,
                'amount' => Money::fromCents($amountCents),
                'used_at' => $usedAt === null ? Carbon::now() : Carbon::instance($usedAt),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e) && $orderNumber !== null) {
                $existing = CampaignUsage::query()
                    ->where('campaign_id', $campaignId)
                    ->where('member_id', $memberId)
                    ->where('order_number', $orderNumber)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    public function countForMember(int $campaignId, int $memberId): int
    {
        return CampaignUsage::query()
            ->where('campaign_id', $campaignId)
            ->where('member_id', $memberId)
            ->count();
    }

    public function findUsed(int $campaignId, int $memberId, string $orderNumber): ?CampaignUsage
    {
        return CampaignUsage::query()
            ->where('campaign_id', $campaignId)
            ->where('member_id', $memberId)
            ->where('order_number', $orderNumber)
            ->first();
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000'
            || $driverCode === 1062
            || $driverCode === 19
            || str_contains($e->getMessage(), 'UNIQUE constraint failed')
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
