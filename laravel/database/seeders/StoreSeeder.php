<?php

namespace Database\Seeders;

use App\Domain\Store\Models\Region;
use App\Domain\Store\Services\RegionService;
use Illuminate\Database\Seeder;

/**
 * 区域基础数据 seeding（幂等，按 code 判断）。
 *
 * 门店数据依赖 seller/stores 业务数据，seeder 不造门店；绑区由管理端调用 RegionService::assignStore 完成。
 */
class StoreSeeder extends Seeder
{
    /**
     * 区域树定义（父节点必须排在子节点之前）。
     *
     * @var array<int, array{code: string, name: string, parent_code: string|null}>
     */
    public const REGIONS = [
        ['code' => 'RG-EAST', 'name' => '华东大区', 'parent_code' => null],
        ['code' => 'RG-EAST-SH', 'name' => '上海区', 'parent_code' => 'RG-EAST'],
        ['code' => 'RG-EAST-ZJ', 'name' => '浙江区', 'parent_code' => 'RG-EAST'],
        ['code' => 'RG-SOUTH', 'name' => '华南大区', 'parent_code' => null],
        ['code' => 'RG-SOUTH-GD', 'name' => '广东区', 'parent_code' => 'RG-SOUTH'],
    ];

    public function run(RegionService $regions): void
    {
        $this->seedRegions($regions);
    }

    /**
     * @return array<string, Region>
     */
    public function seedRegions(RegionService $regions): array
    {
        $seeded = [];

        foreach (self::REGIONS as $definition) {
            $region = Region::query()->where('code', $definition['code'])->first();

            if ($region === null) {
                $parent = $definition['parent_code'] === null
                    ? null
                    : ($seeded[$definition['parent_code']] ?? null);

                $region = $regions->create([
                    'parent_id' => $parent?->getKey(),
                    'code' => $definition['code'],
                    'name' => $definition['name'],
                    'status' => Region::STATUS_ACTIVE,
                ]);
            }

            $seeded[$definition['code']] = $region;
        }

        return $seeded;
    }
}
