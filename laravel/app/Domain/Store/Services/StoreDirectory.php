<?php

namespace App\Domain\Store\Services;

use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Exceptions\StoreNotOrderableException;
use App\Domain\Store\Models\Region;
use App\Models\Store;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 门店目录服务：按区域列门店、解析查询范围、营业时间解析与开放状态判断、区域汇总。
 *
 * 营业时间 JSON 约定（stores.business_hours）：
 * {
 *   "timezone": "Asia/Shanghai",
 *   "default": [["09:00", "22:00"]],
 *   "mon": [["09:00", "22:00"]],
 *   "sun": [["18:00", "02:00"]]
 * }
 * - 键：mon/tue/wed/thu/fri/sat/sun（也接受 monday..sunday 与 1..7），default 为兜底；
 * - 值：时间段数组，[] 表示当天闭店；单个时间段 close <= open 视为跨天营业（如 18:00-02:00）；
 * - 未配置 business_hours 视为「全天营业」，由 stores.status 决定是否可下单。
 *
 * 说明：既有 App\Models\Store 不改，本服务只读该模型（不依赖其控制器）。
 */
class StoreDirectory
{
    public const STATE_OPEN = 'open';

    public const STATE_CLOSED = 'closed';

    public const STATE_SUSPENDED = 'suspended';

    public const STATE_OUTSIDE_BUSINESS_HOURS = 'outside_business_hours';

    public const STATE_DELETED = 'deleted';

    /** 支持的门店范围参数（resolveScope）。 */
    public const SCOPE_KEYS = [
        'region_id',
        'region_code',
        'include_descendants',
        'store_id',
        'store_code',
        'status',
        'open_only',
    ];

    private const DAY_INDEX = [
        'mon' => 1, 'monday' => 1, '1' => 1,
        'tue' => 2, 'tuesday' => 2, '2' => 2,
        'wed' => 3, 'wednesday' => 3, '3' => 3,
        'thu' => 4, 'thursday' => 4, '4' => 4,
        'fri' => 5, 'friday' => 5, '5' => 5,
        'sat' => 6, 'saturday' => 6, '6' => 6,
        'sun' => 7, 'sunday' => 7, '7' => 7,
    ];

    private const DEFAULT_KEYS = ['default', '*', 'all'];

    private RegionService $regions;

    public function __construct(?RegionService $regions = null)
    {
        $this->regions = $regions ?? new RegionService;
    }

    /**
     * 按区域列门店（默认含下级区域）。
     *
     * @return Builder<Store>
     */
    public function listByRegion(Region $region, bool $includeDescendants = true): Builder
    {
        $ids = $includeDescendants
            ? $this->regions->descendantIds($region, true)
            : [(int) $region->getKey()];

        return Store::query()->whereIn('region_id', $ids)->orderBy('id');
    }

    /**
     * 解析门店查询范围，供列表/API/汇总复用。
     *
     * @param  array<string, mixed>  $scope
     * @return Builder<Store>
     */
    public function resolveScope(array $scope = []): Builder
    {
        $unknown = array_diff(array_keys($scope), self::SCOPE_KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException('未知的门店范围参数：'.implode(', ', $unknown));
        }

        $query = Store::query();

        $regionIds = $this->resolveRegionIds($scope);
        if ($regionIds !== null) {
            $query->whereIn('region_id', $regionIds);
        }

        if (array_key_exists('store_id', $scope)) {
            $query->where('id', (int) $scope['store_id']);
        }

        if (array_key_exists('store_code', $scope)) {
            $query->where('store_code', (string) $scope['store_code']);
        }

        if (! empty($scope['open_only'])) {
            $query->where('status', StoreStatus::OPEN);
        } elseif (array_key_exists('status', $scope)) {
            $query->where('status', (string) $scope['status']);
        }

        return $query;
    }

    /**
     * 解析营业时间（数组 / JSON 字符串 / "09:00-22:00" 简写）。
     *
     * @return array{
     *     timezone: string|null,
     *     has_schedule: bool,
     *     days: array<int, list<array{open: int, close: int, open_label: string, close_label: string, overnight: bool}>>
     * }
     */
    public function parseBusinessHours(mixed $raw): array
    {
        $decoded = $this->decodeHours($raw);
        if ($decoded === null) {
            return ['timezone' => null, 'has_schedule' => false, 'days' => []];
        }

        $timezone = isset($decoded['timezone']) && is_string($decoded['timezone']) ? $decoded['timezone'] : null;

        /** @var array<int, list<array{open: int, close: int, open_label: string, close_label: string, overnight: bool}>|null> $days */
        $days = array_fill_keys(range(1, 7), null);
        $default = null;

        foreach ($decoded as $key => $periods) {
            $normalizedKey = strtolower(trim((string) $key));

            if ($normalizedKey === 'timezone') {
                continue;
            }

            if (in_array($normalizedKey, self::DEFAULT_KEYS, true)) {
                $default = $this->parsePeriods($periods);

                continue;
            }

            $day = self::DAY_INDEX[$normalizedKey] ?? null;
            if ($day === null) {
                throw new InvalidArgumentException("未知的营业时间键：{$key}");
            }

            $days[$day] = $this->parsePeriods($periods);
        }

        $hasSchedule = $default !== null;

        foreach (range(1, 7) as $day) {
            if ($days[$day] === null) {
                $days[$day] = $default ?? [];
            }
            if ($days[$day] !== []) {
                $hasSchedule = true;
            }
        }

        return ['timezone' => $timezone, 'has_schedule' => $hasSchedule, 'days' => $days];
    }

    /**
     * 门店营业时间（已解析）。
     *
     * @param  Store|array<string, mixed>  $store
     * @return array{
     *     timezone: string|null,
     *     has_schedule: bool,
     *     days: array<int, list<array{open: int, close: int, open_label: string, close_label: string, overnight: bool}>>
     * }
     */
    public function businessHours(Store|array $store): array
    {
        return $this->parseBusinessHours($this->attribute($store, 'business_hours'));
    }

    /**
     * 门店当前状态：open / closed / suspended / outside_business_hours / deleted。
     *
     * @param  Store|array<string, mixed>  $store
     */
    public function openState(Store|array $store, DateTimeInterface|string|null $now = null): string
    {
        if ($this->isDeleted($store)) {
            return self::STATE_DELETED;
        }

        $status = (string) ($this->attribute($store, 'status') ?? StoreStatus::OPEN);
        if ($status !== StoreStatus::OPEN) {
            return in_array($status, StoreStatus::all(), true) ? $status : self::STATE_CLOSED;
        }

        $hours = $this->businessHours($store);
        if (! $hours['has_schedule']) {
            return self::STATE_OPEN;
        }

        $local = $this->localNow($hours['timezone'], $now);
        $day = (int) $local->dayOfWeekIso;
        $minuteOfDay = (int) $local->hour * 60 + (int) $local->minute;

        foreach ($hours['days'][$day] ?? [] as $period) {
            if ($minuteOfDay >= $period['open'] && $minuteOfDay < $period['close']) {
                return self::STATE_OPEN;
            }
        }

        // 前一天跨天营业（如 18:00-02:00）延续到当天凌晨
        $previousDay = $day === 1 ? 7 : $day - 1;
        foreach ($hours['days'][$previousDay] ?? [] as $period) {
            if ($period['overnight'] && $minuteOfDay + 1440 < $period['close']) {
                return self::STATE_OPEN;
            }
        }

        return self::STATE_OUTSIDE_BUSINESS_HOURS;
    }

    /**
     * 此刻是否营业中（status=open 且处于营业时间段）。
     *
     * @param  Store|array<string, mixed>  $store
     */
    public function isOpenNow(Store|array $store, DateTimeInterface|string|null $now = null): bool
    {
        return $this->openState($store, $now) === self::STATE_OPEN;
    }

    /**
     * 是否允许下单（闭店/停业/非营业时间/已删除都拒绝）。
     *
     * @param  Store|array<string, mixed>  $store
     */
    public function canOrder(Store|array $store, DateTimeInterface|string|null $now = null): bool
    {
        return $this->isOpenNow($store, $now);
    }

    /**
     * 下单前置校验：不可下单时抛 StoreNotOrderableException。
     *
     * @param  Store|array<string, mixed>  $store
     */
    public function assertOrderable(Store|array $store, DateTimeInterface|string|null $now = null): void
    {
        $state = $this->openState($store, $now);

        if ($state !== self::STATE_OPEN) {
            throw StoreNotOrderableException::forState((int) $this->attribute($store, 'id'), $state);
        }
    }

    /**
     * 区域汇总：门店数按营业状态分组（默认含下级区域）。
     *
     * @return array{
     *     region_id: int,
     *     region_code: string,
     *     region_name: string,
     *     include_descendants: bool,
     *     store_count: int,
     *     open_count: int,
     *     closed_count: int,
     *     suspended_count: int,
     *     other_count: int,
     *     store_ids: list<int>
     * }
     */
    public function summaryByRegion(Region $region, bool $includeDescendants = true): array
    {
        $base = $this->listByRegion($region, $includeDescendants)->toBase();

        $counts = [];
        $rows = (clone $base)
            ->reorder()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get();

        foreach ($rows as $row) {
            $counts[(string) $row->status] = (int) $row->total;
        }

        $storeIds = (clone $base)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $storeCount = count($storeIds);
        $knownCount = (int) (($counts[StoreStatus::OPEN] ?? 0)
            + ($counts[StoreStatus::CLOSED] ?? 0)
            + ($counts[StoreStatus::SUSPENDED] ?? 0));

        return [
            'region_id' => (int) $region->getKey(),
            'region_code' => (string) $region->code,
            'region_name' => (string) $region->name,
            'include_descendants' => $includeDescendants,
            'store_count' => $storeCount,
            'open_count' => (int) ($counts[StoreStatus::OPEN] ?? 0),
            'closed_count' => (int) ($counts[StoreStatus::CLOSED] ?? 0),
            'suspended_count' => (int) ($counts[StoreStatus::SUSPENDED] ?? 0),
            'other_count' => max(0, $storeCount - $knownCount),
            'store_ids' => array_values($storeIds),
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array<int, int>|null null 表示不按区域过滤；空数组表示区域不存在（查不到任何门店）
     */
    private function resolveRegionIds(array $scope): ?array
    {
        if (! array_key_exists('region_id', $scope) && ! array_key_exists('region_code', $scope)) {
            return null;
        }

        $region = array_key_exists('region_id', $scope)
            ? Region::query()->find((int) $scope['region_id'])
            : Region::query()->where('code', (string) $scope['region_code'])->first();

        if (! $region instanceof Region) {
            return [];
        }

        $includeDescendants = ! array_key_exists('include_descendants', $scope)
            || (bool) $scope['include_descendants'];

        return $includeDescendants
            ? $this->regions->descendantIds($region, true)
            : [(int) $region->getKey()];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeHours(mixed $raw): ?array
    {
        if ($raw === null || $raw === '' || $raw === [] || $raw === 'null') {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw)) {
            throw new InvalidArgumentException('营业时间格式不正确');
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 兼容简写："09:00-22:00" / "09:00-22:00,18:00-02:00"（对所有天生效）
        return ['default' => $raw];
    }

    /**
     * @return list<array{open: int, close: int, open_label: string, close_label: string, overnight: bool}>
     */
    private function parsePeriods(mixed $periods): array
    {
        if ($periods === null || $periods === '' || $periods === []) {
            return [];
        }

        if (is_string($periods)) {
            $periods = array_values(array_filter(
                array_map('trim', explode(',', $periods)),
                fn (string $period): bool => $period !== ''
            ));
        }

        if (! is_array($periods)) {
            throw new InvalidArgumentException('营业时间段格式不正确');
        }

        $result = [];

        foreach ($periods as $period) {
            [$openLabel, $closeLabel] = $this->periodLabels($period);

            $open = $this->parseMinutes($openLabel);
            $close = $this->parseMinutes($closeLabel);
            $overnight = $close <= $open;

            if ($overnight) {
                // close <= open：跨天营业，如 18:00-02:00 -> 1080..1560
                $close += 1440;
            }

            $result[] = [
                'open' => $open,
                'close' => $close,
                'open_label' => $openLabel,
                'close_label' => $closeLabel,
                'overnight' => $overnight,
            ];
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function periodLabels(mixed $period): array
    {
        if (is_string($period)) {
            $parts = explode('-', $period);
            if (count($parts) !== 2) {
                throw new InvalidArgumentException("营业时间段格式不正确：{$period}");
            }

            return [trim($parts[0]), trim($parts[1])];
        }

        if (is_array($period)) {
            $openLabel = trim((string) ($period['open'] ?? $period[0] ?? ''));
            $closeLabel = trim((string) ($period['close'] ?? $period[1] ?? ''));
            if ($openLabel === '' || $closeLabel === '') {
                throw new InvalidArgumentException('营业时间段缺少 open/close');
            }

            return [$openLabel, $closeLabel];
        }

        throw new InvalidArgumentException('营业时间段格式不正确');
    }

    private function parseMinutes(string $label): int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $label, $matches) !== 1) {
            throw new InvalidArgumentException("时间格式不正确：{$label}");
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour > 23 || $minute > 59) {
            throw new InvalidArgumentException("时间格式不正确：{$label}");
        }

        return $hour * 60 + $minute;
    }

    private function localNow(?string $timezone, DateTimeInterface|string|null $now): Carbon
    {
        $timezoneName = $timezone ?: (string) config('app.timezone', 'Asia/Shanghai');

        try {
            $zone = new DateTimeZone($timezoneName);
        } catch (\Exception) {
            $zone = new DateTimeZone('Asia/Shanghai');
        }

        if ($now === null) {
            return Carbon::now($zone);
        }

        return Carbon::parse($now)->setTimezone($zone);
    }

    /**
     * @param  Store|array<string, mixed>  $store
     */
    private function attribute(Store|array $store, string $key): mixed
    {
        if ($store instanceof Store) {
            return $store->getAttribute($key);
        }

        return $store[$key] ?? null;
    }

    /**
     * @param  Store|array<string, mixed>  $store
     */
    private function isDeleted(Store|array $store): bool
    {
        if ($store instanceof Store) {
            return $store->trashed();
        }

        return ($store['deleted_at'] ?? null) !== null;
    }
}
