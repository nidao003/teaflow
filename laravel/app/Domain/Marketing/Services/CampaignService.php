<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Services;

use App\Domain\Marketing\Enums\CampaignScope;
use App\Domain\Marketing\Enums\CampaignStatus;
use App\Domain\Marketing\Enums\CampaignType;
use App\Domain\Marketing\Exceptions\CampaignException;
use App\Domain\Marketing\Models\Campaign;
use App\Domain\Marketing\Models\CampaignSku;
use App\Domain\Marketing\Support\Money;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 营销活动服务：创建/启用、资格校验、优惠计算与叠加。
 *
 * 资格校验维度：状态 active、时间窗、门店范围、商品范围、金额门槛、每人次数。
 * rules_json 结构见 Campaign 模型注释。
 *
 * 叠加规则（rules_json）：
 * - stackable=false（默认）：该活动与其他活动互斥；
 * - stackable=true：可与其它 stackable 活动叠加，但同一 stack_group 内互斥；
 * - 选择顺序：priority 降序 → 折扣金额降序 → id 升序，最高优先级活动为主活动。
 */
class CampaignService
{
    public function __construct(
        protected UsageService $usages
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createCampaign(array $attributes): Campaign
    {
        $name = $this->requireString($attributes['name'] ?? null, '活动名称', 64);

        $type = $attributes['type'] ?? null;
        if (! is_string($type)) {
            throw new InvalidArgumentException('活动类型不能为空');
        }
        CampaignType::assertValid($type);

        $scope = $attributes['scope'] ?? CampaignScope::ALL;
        if (! is_string($scope)) {
            throw new InvalidArgumentException('非法活动范围');
        }
        CampaignScope::assertValid($scope);

        $storeId = null;
        if ($scope === CampaignScope::STORE) {
            $storeId = $this->requirePositiveInt($attributes['store_id'] ?? null, 'store_id');
        } elseif (($attributes['store_id'] ?? null) !== null) {
            $storeId = $this->requirePositiveInt($attributes['store_id'], 'store_id');
        }

        $rules = $attributes['rules_json'] ?? [];
        if (! is_array($rules)) {
            throw CampaignException::invalidRules($type, 'rules_json 必须是数组');
        }
        $this->assertRules($type, $rules);

        $startAt = $this->optionalDateTime($attributes['start_at'] ?? null);
        $endAt = $this->optionalDateTime($attributes['end_at'] ?? null);
        if ($startAt !== null && $endAt !== null && $startAt->gt($endAt)) {
            throw CampaignException::invalidWindow($startAt->toDateTimeString(), $endAt->toDateTimeString());
        }

        $status = $attributes['status'] ?? CampaignStatus::DRAFT;
        if (! is_string($status)) {
            throw new InvalidArgumentException('非法活动状态');
        }
        CampaignStatus::assertValid($status);

        $campaign = Campaign::query()->create([
            'name' => $name,
            'type' => $type,
            'scope' => $scope,
            'store_id' => $storeId,
            'rules_json' => $rules,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => $status,
            'priority' => (int) ($attributes['priority'] ?? 0),
        ]);

        $skus = $attributes['skus'] ?? [];
        if (! is_array($skus)) {
            throw new InvalidArgumentException('skus 必须是数组');
        }

        $seen = [];
        foreach ($skus as $skuId) {
            $skuId = $this->requirePositiveInt($skuId, 'skus');
            if (in_array($skuId, $seen, true)) {
                continue;
            }
            CampaignSku::query()->create([
                'campaign_id' => (int) $campaign->getKey(),
                'product_sku_id' => $skuId,
            ]);
            $seen[] = $skuId;
        }

        return $campaign->refresh();
    }

    public function activate(int $campaignId): Campaign
    {
        return $this->changeStatus($campaignId, CampaignStatus::ACTIVE, [CampaignStatus::DRAFT, CampaignStatus::DISABLED]);
    }

    public function disable(int $campaignId): Campaign
    {
        return $this->changeStatus($campaignId, CampaignStatus::DISABLED, [CampaignStatus::DRAFT, CampaignStatus::ACTIVE]);
    }

    public function find(int $campaignId): Campaign
    {
        return Campaign::query()->findOrFail($campaignId);
    }

    /**
     * 活动适用的商品 SKU 列表；空数组表示全场通用。
     *
     * @return list<int>
     */
    public function skuIdsFor(Campaign $campaign): array
    {
        return $campaign->skus()
            ->whereNotNull('product_sku_id')
            ->pluck('product_sku_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * 活动资格校验。
     *
     * @param  list<int|string>  $skuIds  订单包含的商品 SKU
     * @param  int|float|string  $amount  订单金额
     */
    public function eligible(
        Campaign $campaign,
        ?int $memberId,
        ?int $storeId,
        array $skuIds,
        int|float|string $amount,
        ?DateTimeInterface $now = null
    ): bool {
        if ((string) $campaign->status !== CampaignStatus::ACTIVE) {
            return false;
        }

        $at = $now === null ? Carbon::now() : Carbon::instance($now);
        if ($campaign->start_at !== null && $campaign->start_at->gt($at)) {
            return false;
        }
        if ($campaign->end_at !== null && $campaign->end_at->lt($at)) {
            return false;
        }

        if ((string) $campaign->scope === CampaignScope::STORE) {
            if ($storeId === null || (int) $campaign->store_id !== $storeId) {
                return false;
            }
        }

        $campaignSkuIds = $this->skuIdsFor($campaign);
        if ($campaignSkuIds !== []) {
            $normalized = array_map(static fn ($id): int => (int) $id, $skuIds);
            if (array_intersect($campaignSkuIds, $normalized) === []) {
                return false;
            }
        }

        $rules = $campaign->rules_json ?? [];
        $amountCents = Money::toCents($amount);
        $minAmountCents = isset($rules['min_amount']) ? Money::toCents($rules['min_amount']) : 0;
        if ($amountCents < $minAmountCents) {
            return false;
        }

        $maxPerMember = isset($rules['max_per_member']) ? (int) $rules['max_per_member'] : 0;
        if ($maxPerMember > 0) {
            if ($memberId === null || $memberId <= 0) {
                return false;
            }
            if ($this->usages->countForMember((int) $campaign->getKey(), $memberId) >= $maxPerMember) {
                return false;
            }
        }

        return true;
    }

    /**
     * 计算活动优惠金额（正整数分 → 两位小数字符串）。
     *
     * @param  list<array{sku_id:int|string, price:int|float|string, quantity:int}>  $items
     */
    public function calcDiscount(Campaign $campaign, array $items): string
    {
        $validItems = $this->qualifyingItems($campaign, $items);
        $subtotalCents = $this->subtotalCents($validItems);
        $rules = $campaign->rules_json ?? [];
        $type = (string) $campaign->type;
        $discountCents = 0;

        switch ($type) {
            case CampaignType::FULL_REDUCTION:
                $bestMin = -1;
                foreach (($rules['thresholds'] ?? []) as $threshold) {
                    if (! is_array($threshold)) {
                        continue;
                    }
                    $min = isset($threshold['min_amount']) ? Money::toCents($threshold['min_amount']) : 0;
                    if ($subtotalCents >= $min && $min > $bestMin) {
                        $bestMin = $min;
                        $discountCents = isset($threshold['discount_amount'])
                            ? Money::toCents($threshold['discount_amount'])
                            : 0;
                    }
                }
                break;

            case CampaignType::PERCENT:
                $percent = isset($rules['percent']) && is_numeric($rules['percent'])
                    ? (int) $rules['percent']
                    : 100;
                $discountCents = (int) round($subtotalCents * (100 - $percent) / 100);
                if (isset($rules['max_discount'])) {
                    $discountCents = min($discountCents, Money::toCents($rules['max_discount']));
                }
                break;

            case CampaignType::SECOND_HALF:
                foreach ($validItems as $item) {
                    $pairs = intdiv((int) $item['quantity'], 2);
                    if ($pairs > 0) {
                        $discountCents += (int) round(Money::toCents($item['price']) * $pairs / 2);
                    }
                }
                break;

            case CampaignType::GIFT:
                $discountCents = 0;
                break;
        }

        return Money::fromCents(max(0, min($discountCents, $subtotalCents)));
    }

    /**
     * 赠品信息：非赠品类活动、或未达门槛返回 null。
     *
     * @return array{product_sku_id:int, quantity:int}|null
     */
    public function gift(Campaign $campaign, int|float|string $amount): ?array
    {
        if ((string) $campaign->type !== CampaignType::GIFT) {
            return null;
        }

        $rules = $campaign->rules_json ?? [];
        $minAmountCents = isset($rules['min_amount']) ? Money::toCents($rules['min_amount']) : 0;
        if (Money::toCents($amount) < $minAmountCents) {
            return null;
        }

        $skuId = isset($rules['gift_sku_id']) ? (int) $rules['gift_sku_id'] : 0;
        if ($skuId <= 0) {
            return null;
        }

        $quantity = isset($rules['gift_quantity']) ? (int) $rules['gift_quantity'] : 1;

        return ['product_sku_id' => $skuId, 'quantity' => max(1, $quantity)];
    }

    /**
     * 多活动叠加求解：返回选中活动、合计优惠与赠品。
     *
     * @param  iterable<Campaign>  $campaigns
     * @param  list<array{sku_id:int|string, price:int|float|string, quantity:int}>  $items
     * @return array{
     *     campaigns: list<Campaign>,
     *     discount: string,
     *     gifts: list<array{campaign_id:int, product_sku_id:int, quantity:int}>,
     *     stacked: bool
     * }
     */
    public function resolve(
        iterable $campaigns,
        array $items,
        ?int $memberId = null,
        ?int $storeId = null,
        ?DateTimeInterface $now = null
    ): array {
        $normalizedItems = $this->normalizeItems($items);
        $subtotalCents = $this->subtotalCents($normalizedItems);
        $amount = Money::fromCents($subtotalCents);
        $skuIds = array_map(static fn (array $item): int => (int) $item['sku_id'], $normalizedItems);

        /** @var list<array{campaign: Campaign, discount: string, cents: int}> $candidates */
        $candidates = [];
        foreach ($campaigns as $campaign) {
            if (! $this->eligible($campaign, $memberId, $storeId, $skuIds, $amount, $now)) {
                continue;
            }
            $discount = $this->calcDiscount($campaign, $normalizedItems);
            $candidates[] = [
                'campaign' => $campaign,
                'discount' => $discount,
                'cents' => Money::toCents($discount),
            ];
        }

        if ($candidates === []) {
            return ['campaigns' => [], 'discount' => '0.00', 'gifts' => [], 'stacked' => false];
        }

        usort($candidates, static function (array $left, array $right): int {
            if ($left['campaign']->priority !== $right['campaign']->priority) {
                return $right['campaign']->priority <=> $left['campaign']->priority;
            }
            if ($left['cents'] !== $right['cents']) {
                return $right['cents'] <=> $left['cents'];
            }

            return $left['campaign']->getKey() <=> $right['campaign']->getKey();
        });

        $primary = $candidates[0];
        $selected = [$primary];
        $groups = [$this->stackGroup($primary['campaign'])];

        if ($this->isStackable($primary['campaign'])) {
            foreach (array_slice($candidates, 1) as $candidate) {
                if (! $this->isStackable($candidate['campaign'])) {
                    continue;
                }
                $group = $this->stackGroup($candidate['campaign']);
                if (in_array($group, $groups, true)) {
                    continue;
                }
                $groups[] = $group;
                $selected[] = $candidate;
            }
        }

        $totalCents = 0;
        $gifts = [];
        foreach ($selected as $row) {
            $totalCents += $row['cents'];
            if ((string) $row['campaign']->type === CampaignType::GIFT) {
                $gift = $this->gift($row['campaign'], $amount);
                if ($gift !== null) {
                    $gifts[] = ['campaign_id' => (int) $row['campaign']->getKey()] + $gift;
                }
            }
        }

        $campaignsOut = array_map(static fn (array $row): Campaign => $row['campaign'], $selected);

        return [
            'campaigns' => $campaignsOut,
            'discount' => Money::fromCents(min($totalCents, $subtotalCents)),
            'gifts' => $gifts,
            'stacked' => count($selected) > 1,
        ];
    }

    /**
     * @param  list<array{sku_id:int|string, price:int|float|string, quantity:int}>  $items
     */
    public function itemsSubtotal(array $items): string
    {
        return Money::fromCents($this->subtotalCents($this->normalizeItems($items)));
    }

    protected function changeStatus(int $campaignId, string $to, array $allowedFrom): Campaign
    {
        return DB::transaction(function () use ($campaignId, $to, $allowedFrom): Campaign {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaignId);
            $from = (string) $campaign->status;

            if ($from === $to) {
                return $campaign;
            }
            if (! in_array($from, $allowedFrom, true)) {
                throw CampaignException::invalidStatusTransition($campaignId, $from, $to);
            }

            $campaign->fill(['status' => $to])->save();

            return $campaign->refresh();
        });
    }

    /**
     * 活动规则结构校验（金额按分比较）。
     *
     * @param  array<string, mixed>  $rules
     */
    protected function assertRules(string $type, array $rules): void
    {
        if (isset($rules['min_amount']) && Money::toCents($rules['min_amount']) < 0) {
            throw CampaignException::invalidRules($type, 'min_amount 不能为负数');
        }
        if (isset($rules['max_per_member']) && (int) $rules['max_per_member'] < 0) {
            throw CampaignException::invalidRules($type, 'max_per_member 不能为负数');
        }

        switch ($type) {
            case CampaignType::FULL_REDUCTION:
                $thresholds = $rules['thresholds'] ?? null;
                if (! is_array($thresholds) || $thresholds === []) {
                    throw CampaignException::invalidRules($type, '缺少 thresholds');
                }
                foreach ($thresholds as $threshold) {
                    if (! is_array($threshold)) {
                        throw CampaignException::invalidRules($type, 'thresholds 元素必须是数组');
                    }
                    if (! isset($threshold['min_amount']) || ! isset($threshold['discount_amount'])) {
                        throw CampaignException::invalidRules($type, 'thresholds 需要 min_amount 与 discount_amount');
                    }
                    if (Money::toCents($threshold['min_amount']) <= 0) {
                        throw CampaignException::invalidRules($type, 'thresholds.min_amount 必须大于 0');
                    }
                    if (Money::toCents($threshold['discount_amount']) <= 0) {
                        throw CampaignException::invalidRules($type, 'thresholds.discount_amount 必须大于 0');
                    }
                }
                break;

            case CampaignType::PERCENT:
                if (! isset($rules['percent']) || ! is_numeric($rules['percent'])) {
                    throw CampaignException::invalidRules($type, '缺少 percent');
                }
                $percent = (int) $rules['percent'];
                if ($percent < 1 || $percent > 100) {
                    throw CampaignException::invalidRules($type, 'percent 必须在 1-100 之间');
                }
                if (isset($rules['max_discount']) && Money::toCents($rules['max_discount']) < 0) {
                    throw CampaignException::invalidRules($type, 'max_discount 不能为负数');
                }
                break;

            case CampaignType::SECOND_HALF:
                if (isset($rules['min_quantity']) && (int) $rules['min_quantity'] <= 0) {
                    throw CampaignException::invalidRules($type, 'min_quantity 必须大于 0');
                }
                break;

            case CampaignType::GIFT:
                if (! isset($rules['gift_sku_id']) || (int) $rules['gift_sku_id'] <= 0) {
                    throw CampaignException::invalidRules($type, '缺少 gift_sku_id');
                }
                if (isset($rules['gift_quantity']) && (int) $rules['gift_quantity'] <= 0) {
                    throw CampaignException::invalidRules($type, 'gift_quantity 必须大于 0');
                }
                break;
        }
    }

    protected function isStackable(Campaign $campaign): bool
    {
        $rules = $campaign->rules_json ?? [];

        return (bool) ($rules['stackable'] ?? false);
    }

    protected function stackGroup(Campaign $campaign): string
    {
        $rules = $campaign->rules_json ?? [];
        $group = $rules['stack_group'] ?? null;

        return is_string($group) && $group !== ''
            ? $group
            : 'campaign-'.$campaign->getKey();
    }

    /**
     * @param  list<array{sku_id:int|string, price:int|float|string, quantity:int}>  $items
     * @return list<array{sku_id:int, price:int|float|string, quantity:int}>
     */
    protected function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                throw new InvalidArgumentException('第 '.($index + 1).' 个商品项格式非法');
            }
            if (! array_key_exists('price', $item)) {
                throw new InvalidArgumentException('第 '.($index + 1).' 个商品项缺少 price');
            }

            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity <= 0) {
                throw new InvalidArgumentException('第 '.($index + 1).' 个商品项 quantity 必须为正整数');
            }
            if (! is_int($item['price']) && ! is_float($item['price']) && ! is_string($item['price'])) {
                throw new InvalidArgumentException('第 '.($index + 1).' 个商品项 price 格式非法');
            }
            if (Money::toCents($item['price']) < 0) {
                throw new InvalidArgumentException('第 '.($index + 1).' 个商品项 price 不能为负数');
            }

            $normalized[] = [
                'sku_id' => (int) ($item['sku_id'] ?? 0),
                'price' => $item['price'],
                'quantity' => $quantity,
            ];
        }

        return $normalized;
    }

    /**
     * @param  list<array{sku_id:int|string, price:int|float|string, quantity:int}>  $items
     * @return list<array{sku_id:int, price:int|float|string, quantity:int}>
     */
    protected function qualifyingItems(Campaign $campaign, array $items): array
    {
        $normalized = $this->normalizeItems($items);
        $skuIds = $this->skuIdsFor($campaign);

        if ($skuIds === []) {
            return $normalized;
        }

        return array_values(array_filter(
            $normalized,
            static fn (array $item): bool => in_array((int) $item['sku_id'], $skuIds, true)
        ));
    }

    /**
     * @param  list<array{sku_id:int, price:int|float|string, quantity:int}>  $items
     */
    protected function subtotalCents(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            $total += Money::toCents($item['price']) * (int) $item['quantity'];
        }

        return $total;
    }

    protected function requirePositiveInt(mixed $value, string $field): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException($field.' 必须为正整数');
        }

        $int = (int) $value;
        if ($int <= 0) {
            throw new InvalidArgumentException($field.' 必须为正整数');
        }

        return $int;
    }

    protected function requireString(mixed $value, string $label, int $maxLength): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException($label.'不能为空');
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException($label.'不能为空');
        }
        if (mb_strlen($trimmed) > $maxLength) {
            throw new InvalidArgumentException($label.'长度不能超过 '.$maxLength.' 个字符');
        }

        return $trimmed;
    }

    protected function optionalDateTime(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }
        if (is_string($value)) {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                throw new InvalidArgumentException('活动时间格式非法：'.$value);
            }
        }

        throw new InvalidArgumentException('活动时间格式非法');
    }
}
