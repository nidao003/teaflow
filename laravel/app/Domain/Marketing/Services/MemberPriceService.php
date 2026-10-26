<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Services;

use App\Domain\Marketing\Enums\Status;
use App\Domain\Marketing\Models\MemberPriceRule;
use App\Domain\Marketing\Support\Money;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

/**
 * 会员价服务：按「SKU + 会员等级 + 日期」取最低会员价，未命中返回商品原价。
 *
 * 说明：
 * - 原价读取 product_skus.price（只读，不修改商品域数据）；
 * - 会员价不会高于原价（取两者最低），避免配置异常导致会员价更贵；
 * - 生效窗口按日期（Y-m-d）比较，from/to 为空分别表示不限。
 */
class MemberPriceService
{
    public function originalPrice(int $productSkuId): string
    {
        $this->assertPositiveInt($productSkuId, 'product_sku_id');

        $price = DB::table('product_skus')->where('id', $productSkuId)->value('price');
        if ($price === null) {
            throw new InvalidArgumentException('商品 SKU 不存在：'.$productSkuId);
        }

        return Money::fromCents(Money::toCents($this->scalarToString($price)));
    }

    /**
     * 会员价：有效期内且启用规则中的最低价，且不高于原价；无命中返回原价。
     */
    public function effectivePrice(int $productSkuId, ?int $memberLevelId, ?DateTimeInterface $at = null): string
    {
        $original = $this->originalPrice($productSkuId);

        if ($memberLevelId === null) {
            return $original;
        }

        $date = $at === null ? Carbon::now()->toDateString() : Carbon::instance($at)->toDateString();
        $rules = MemberPriceRule::query()
            ->where('product_sku_id', $productSkuId)
            ->where('member_level_id', $memberLevelId)
            ->where('status', Status::ACTIVE)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_from')->orWhere('effective_from', '<=', $date);
            })
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->get();

        if ($rules->isEmpty()) {
            return $original;
        }

        $lowestCents = Money::toCents($original);
        foreach ($rules as $rule) {
            $ruleCents = Money::toCents((string) $rule->price);
            if ($ruleCents < $lowestCents) {
                $lowestCents = $ruleCents;
            }
        }

        return Money::fromCents($lowestCents);
    }

    /**
     * 按会员当前等级取价。
     */
    public function priceForMember(int $memberId, int $productSkuId, ?DateTimeInterface $at = null): string
    {
        $levelId = DB::table('members')->where('id', $memberId)->value('level_id');

        return $this->effectivePrice(
            $productSkuId,
            $levelId === null ? null : (int) $levelId,
            $at
        );
    }

    /**
     * 新增/更新会员价规则（按 unique(product_sku_id, member_level_id, effective_from)）。
     */
    public function setPrice(
        int $productSkuId,
        int $memberLevelId,
        int|float|string $price,
        ?string $effectiveFrom = null,
        ?string $effectiveTo = null,
        int $status = Status::ACTIVE
    ): MemberPriceRule {
        $this->assertPositiveInt($productSkuId, 'product_sku_id');
        $this->assertPositiveInt($memberLevelId, 'member_level_id');
        Status::assertValid($status);

        $priceCents = Money::toCents($price);
        if ($priceCents < 0) {
            throw new InvalidArgumentException('会员价不能为负数');
        }

        $from = $this->normalizeDateOrNull($effectiveFrom);
        $to = $this->normalizeDateOrNull($effectiveTo);
        if ($from !== null && $to !== null && $from > $to) {
            throw new InvalidArgumentException('会员价生效开始日期不能晚于结束日期');
        }

        return MemberPriceRule::query()->updateOrCreate(
            [
                'product_sku_id' => $productSkuId,
                'member_level_id' => $memberLevelId,
                'effective_from' => $from,
            ],
            [
                'price' => Money::fromCents($priceCents),
                'effective_to' => $to,
                'status' => $status,
            ]
        );
    }

    /**
     * @return Collection<int, MemberPriceRule>
     */
    public function rulesForSku(int $productSkuId): Collection
    {
        return MemberPriceRule::query()
            ->where('product_sku_id', $productSkuId)
            ->orderBy('member_level_id')
            ->orderBy('effective_from')
            ->get();
    }

    protected function normalizeDateOrNull(?string $date): ?string
    {
        if ($date === null || trim($date) === '') {
            return null;
        }

        $raw = trim($date);

        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $raw);
        } catch (Throwable) {
            throw new InvalidArgumentException('日期格式非法（Y-m-d）：'.$raw);
        }

        if ($parsed === null || $parsed->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException('日期格式非法（Y-m-d）：'.$raw);
        }

        return $parsed->toDateString();
    }

    protected function scalarToString(mixed $value): string
    {
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }

        throw new InvalidArgumentException('商品价格格式非法');
    }

    protected function assertPositiveInt(int $value, string $field): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException($field.' 必须为正整数');
        }
    }
}
