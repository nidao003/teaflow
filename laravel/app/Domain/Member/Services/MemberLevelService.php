<?php

declare(strict_types=1);

namespace App\Domain\Member\Services;

use App\Domain\Member\Enums\Status;
use App\Domain\Member\Models\Member;
use App\Domain\Member\Models\MemberLevel;
use App\Domain\Member\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 会员等级服务：根据成长值（growth）计算并同步等级，支持升级与降级。
 */
class MemberLevelService
{
    /**
     * 取成长值满足的最高启用等级；成长值低于所有门槛时返回 null。
     */
    public function resolveLevel(int|float|string $growth): ?MemberLevel
    {
        $growthCents = Money::toCents($growth);

        $levels = MemberLevel::query()
            ->where('status', Status::ACTIVE)
            ->orderBy('threshold')
            ->orderBy('id')
            ->get();

        $matched = null;
        foreach ($levels as $level) {
            if (Money::toCents((string) $level->threshold) <= $growthCents) {
                $matched = $level;
            }
        }

        return $matched;
    }

    public function resolveLevelFor(Member $member): ?MemberLevel
    {
        return $this->resolveLevel($member->growth ?? 0);
    }

    /**
     * 按当前成长值重算等级并落库（升级、降级都生效）。
     */
    public function syncLevel(Member $member): Member
    {
        return DB::transaction(function () use ($member): Member {
            $fresh = Member::query()->lockForUpdate()->findOrFail($member->getKey());

            return $this->applyLevel($fresh);
        });
    }

    /**
     * 成长值增减后自动重算等级。
     */
    public function addGrowth(Member $member, int|float|string $delta): Member
    {
        $deltaCents = Money::toCents($delta);

        return DB::transaction(function () use ($member, $deltaCents): Member {
            $fresh = Member::query()->lockForUpdate()->findOrFail($member->getKey());

            $nextCents = Money::toCents((string) $fresh->growth) + $deltaCents;
            if ($nextCents < 0) {
                throw new InvalidArgumentException('成长值不能为负数');
            }

            $fresh->fill(['growth' => Money::fromCents($nextCents)])->save();

            return $this->applyLevel($fresh);
        });
    }

    protected function applyLevel(Member $member): Member
    {
        $level = $this->resolveLevel($member->growth ?? 0);

        $member->fill(['level_id' => $level?->getKey()])->save();

        return $member->refresh();
    }
}
