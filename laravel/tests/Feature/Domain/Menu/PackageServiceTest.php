<?php

namespace Tests\Feature\Domain\Menu;

use App\Domain\Menu\Models\Package;
use App\Domain\Product\Models\ProductSku;
use InvalidArgumentException;

class PackageServiceTest extends MenuTestCase
{
    private const STORE_A = 1;

    public function test_create_package_and_expand_composition(): void
    {
        $tea = $this->createSku('18.00', ProductSku::STATUS_ACTIVE, '大杯/全糖');
        $milkTea = $this->createSku('15.00', ProductSku::STATUS_ACTIVE, '中杯/半糖');

        $package = $this->packageService->create([
            'name' => '双人下午茶套餐',
            'price' => 25.00,
        ], [
            ['product_sku_id' => $tea->id, 'qty' => 1],
            ['product_sku_id' => $milkTea->id, 'qty' => 2],
        ]);

        $this->assertSame('25.00', $this->packageService->price($package->id));
        $this->assertSame(Package::STATUS_ON, $package->status);

        $expanded = $this->packageService->expand($package->id);

        $this->assertSame('双人下午茶套餐', $expanded['package']->name);
        $this->assertCount(2, $expanded['items']);

        $teaItem = $this->expandedItem($expanded, $tea->id);
        $this->assertSame('大杯/全糖', $teaItem['spec_key']);
        $this->assertSame(1, $teaItem['qty']);
        $this->assertSame('18.00', $teaItem['unit_price']);
        $this->assertSame('18.00', $teaItem['line_price']);

        $milkTeaItem = $this->expandedItem($expanded, $milkTea->id);
        $this->assertSame(2, $milkTeaItem['qty']);
        $this->assertSame('30.00', $milkTeaItem['line_price']);

        $this->assertSame('48.00', $expanded['members_total']);
        $this->assertDatabaseCount('package_items', 2);
    }

    public function test_update_replaces_items_and_price(): void
    {
        $tea = $this->createSku('18.00');
        $milkTea = $this->createSku('15.00');

        $package = $this->packageService->create([
            'name' => '单人套餐',
            'price' => 18.00,
        ], [
            ['product_sku_id' => $tea->id, 'qty' => 1],
        ]);

        $updated = $this->packageService->update($package->id, ['price' => 20.00], [
            ['product_sku_id' => $milkTea->id, 'qty' => 3],
        ]);

        $this->assertSame('20.00', (string) $updated->price);
        $expanded = $this->packageService->expand($package->id);
        $this->assertCount(1, $expanded['items']);
        $this->assertSame($milkTea->id, $expanded['items'][0]['product_sku_id']);
        $this->assertSame(3, $expanded['items'][0]['qty']);
        $this->assertDatabaseCount('package_items', 1);
    }

    public function test_package_available_when_all_members_available(): void
    {
        $tea = $this->createSku('18.00');
        $milkTea = $this->createSku('15.00');
        $package = $this->packageService->create(['name' => '双人套餐', 'price' => 25.00], [
            ['product_sku_id' => $tea->id, 'qty' => 1],
            ['product_sku_id' => $milkTea->id, 'qty' => 1],
        ]);

        $this->menuService->publish(self::STORE_A, $tea->id);
        $this->menuService->publish(self::STORE_A, $milkTea->id);

        $this->assertTrue($this->packageService->isAvailable($package->id, self::STORE_A, '2026-10-26 12:00:00'));
    }

    public function test_package_unavailable_when_member_off_shelf(): void
    {
        $tea = $this->createSku('18.00');
        $package = $this->packageService->create(['name' => '单人套餐', 'price' => 18.00], [
            ['product_sku_id' => $tea->id, 'qty' => 1],
        ]);

        $this->menuService->publish(self::STORE_A, $tea->id);
        $this->menuService->toggle(self::STORE_A, $tea->id);

        $availability = $this->packageService->availability($package->id, self::STORE_A, '2026-10-26 12:00:00');

        $this->assertFalse($availability['available']);
        $this->assertSame('member_off_shelf', $availability['reason']);
    }

    public function test_package_unavailable_when_member_sold_out(): void
    {
        $tea = $this->createSku('18.00');
        $package = $this->packageService->create(['name' => '单人套餐', 'price' => 18.00], [
            ['product_sku_id' => $tea->id, 'qty' => 1],
        ]);

        $this->menuService->publish(self::STORE_A, $tea->id);
        $this->menuService->setSoldOut(self::STORE_A, $tea->id);

        $availability = $this->packageService->availability($package->id, self::STORE_A, '2026-10-26 12:00:00');

        $this->assertFalse($availability['available']);
        $this->assertSame('member_sold_out', $availability['reason']);
    }

    public function test_package_unavailable_when_member_not_published(): void
    {
        $tea = $this->createSku('18.00');
        $package = $this->packageService->create(['name' => '单人套餐', 'price' => 18.00], [
            ['product_sku_id' => $tea->id, 'qty' => 1],
        ]);

        $availability = $this->packageService->availability($package->id, self::STORE_A, '2026-10-26 12:00:00');

        $this->assertFalse($availability['available']);
        $this->assertSame('member_not_published', $availability['reason']);
    }

    public function test_package_unavailable_when_member_platform_inactive(): void
    {
        $tea = $this->createSku('18.00', ProductSku::STATUS_DISABLED);
        $package = $this->packageService->create(['name' => '单人套餐', 'price' => 18.00], [
            ['product_sku_id' => $tea->id, 'qty' => 1],
        ]);

        $this->menuService->publish(self::STORE_A, $tea->id);

        $availability = $this->packageService->availability($package->id, self::STORE_A, '2026-10-26 12:00:00');

        $this->assertFalse($availability['available']);
        $this->assertSame('member_sku_inactive', $availability['reason']);
    }

    public function test_package_sale_window(): void
    {
        $tea = $this->createSku('18.00');
        $package = $this->packageService->create([
            'name' => '双十一套餐',
            'price' => 11.00,
            'start_at' => '2026-11-01 00:00:00',
            'end_at' => '2026-11-30 23:59:59',
        ], [
            ['product_sku_id' => $tea->id, 'qty' => 1],
        ]);

        $this->menuService->publish(self::STORE_A, $tea->id);

        $this->assertFalse($this->packageService->isAvailable($package->id, self::STORE_A, '2026-10-26 12:00:00'));
        $this->assertSame(
            'not_started',
            $this->packageService->availability($package->id, self::STORE_A, '2026-10-26 12:00:00')['reason']
        );

        $this->assertTrue($this->packageService->isAvailable($package->id, self::STORE_A, '2026-11-15 12:00:00'));

        $this->assertFalse($this->packageService->isAvailable($package->id, self::STORE_A, '2026-12-01 00:00:00'));
        $this->assertSame(
            'ended',
            $this->packageService->availability($package->id, self::STORE_A, '2026-12-01 00:00:00')['reason']
        );
    }

    public function test_package_off_status_is_unavailable(): void
    {
        $tea = $this->createSku('18.00');
        $package = $this->packageService->create([
            'name' => '停售套餐',
            'price' => 18.00,
            'status' => Package::STATUS_OFF,
        ], [
            ['product_sku_id' => $tea->id, 'qty' => 1],
        ]);

        $this->menuService->publish(self::STORE_A, $tea->id);

        $availability = $this->packageService->availability($package->id, self::STORE_A, '2026-10-26 12:00:00');

        $this->assertFalse($availability['available']);
        $this->assertSame('package_off', $availability['reason']);
    }

    public function test_duplicate_member_rejected(): void
    {
        $tea = $this->createSku('18.00');

        $this->expectException(InvalidArgumentException::class);

        $this->packageService->create(['name' => '重复套餐'], [
            ['product_sku_id' => $tea->id, 'qty' => 1],
            ['product_sku_id' => $tea->id, 'qty' => 2],
        ]);
    }

    public function test_empty_members_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->packageService->create(['name' => '空套餐'], []);
    }

    public function test_update_missing_package_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->packageService->update(9999, ['name' => '不存在']);
    }

    /**
     * @param  array{items: array<int, array{product_sku_id: int, spec_key: string, qty: int, unit_price: string, line_price: string}>}  $expanded
     * @return array{product_sku_id: int, spec_key: string, qty: int, unit_price: string, line_price: string}
     */
    private function expandedItem(array $expanded, int $productSkuId): array
    {
        foreach ($expanded['items'] as $item) {
            if ($item['product_sku_id'] === $productSkuId) {
                return $item;
            }
        }

        $this->fail("展开结果中没有 SKU {$productSkuId}");
    }
}
