<?php

namespace Tests\Feature\Domain\Menu;

use App\Domain\Menu\Exceptions\MenuItemNotPublishedException;
use App\Domain\Menu\Models\StoreMenuItem;
use App\Domain\Product\Models\ProductSku;
use InvalidArgumentException;

class StoreMenuServiceTest extends MenuTestCase
{
    private const STORE_A = 1;

    private const STORE_B = 2;

    public function test_publish_defaults_to_platform_price_and_is_idempotent(): void
    {
        $sku = $this->createSku('18.00');

        $item = $this->menuService->publish(self::STORE_A, $sku->id);

        $this->assertSame('18.00', $item->price);
        $this->assertSame(StoreMenuItem::STATUS_ON, $item->status);
        $this->assertFalse($item->sold_out);
        $this->assertSame(self::STORE_A, $item->store_id);

        $again = $this->menuService->publish(self::STORE_A, $sku->id);

        $this->assertSame($item->id, $again->id);
        $this->assertDatabaseCount('store_menu_items', 1);
    }

    public function test_store_price_overrides_platform_price(): void
    {
        $sku = $this->createSku('18.00');

        $this->menuService->publish(self::STORE_A, $sku->id, ['price' => 16.5]);

        $this->assertSame('16.50', $this->menuService->priceFor(self::STORE_A, $sku->id));
        // 其他门店未发布 → 回退平台价
        $this->assertSame('18.00', $this->menuService->priceFor(self::STORE_B, $sku->id));

        $this->menuService->updatePrice(self::STORE_A, $sku->id, 15.8);

        $this->assertSame('15.80', $this->menuService->priceFor(self::STORE_A, $sku->id));
        // 门店价不影响平台价
        $this->assertSame('18.00', (string) $sku->fresh()->price);
    }

    public function test_sold_out_only_affects_its_own_store(): void
    {
        $sku = $this->createSku();

        $this->menuService->publish(self::STORE_A, $sku->id);
        $this->menuService->publish(self::STORE_B, $sku->id);
        $this->menuService->setSoldOut(self::STORE_A, $sku->id);

        $this->assertFalse($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 12:00:00'));
        $this->assertTrue($this->menuService->isAvailable(self::STORE_B, $sku->id, '2026-10-26 12:00:00'));

        $availability = $this->menuService->availability(self::STORE_A, $sku->id, '2026-10-26 12:00:00');
        $this->assertFalse($availability['available']);
        $this->assertSame('sold_out', $availability['reason']);
    }

    public function test_restore_sold_out_makes_item_available_again(): void
    {
        $sku = $this->createSku();
        $this->menuService->publish(self::STORE_A, $sku->id);
        $this->menuService->setSoldOut(self::STORE_A, $sku->id);

        $restored = $this->menuService->restore(self::STORE_A, $sku->id);

        $this->assertFalse($restored->sold_out);
        $this->assertNull($restored->sold_out_at);
        $this->assertTrue($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 12:00:00'));
    }

    public function test_time_window_availability(): void
    {
        $sku = $this->createSku();
        $this->menuService->publish(self::STORE_A, $sku->id, [
            'available_from' => '08:00',
            'available_to' => '22:00',
        ]);

        $this->assertTrue($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 08:00:00'));
        $this->assertTrue($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 09:30:00'));
        $this->assertTrue($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 22:00:00'));
        $this->assertFalse($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 07:59:59'));
        $this->assertFalse($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 22:00:01'));

        $availability = $this->menuService->availability(self::STORE_A, $sku->id, '2026-10-26 23:00:00');
        $this->assertSame('outside_time_window', $availability['reason']);
    }

    public function test_overnight_time_window_availability(): void
    {
        $sku = $this->createSku();
        $this->menuService->publish(self::STORE_A, $sku->id, [
            'available_from' => '22:00',
            'available_to' => '02:00',
        ]);

        $this->assertTrue($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 23:30:00'));
        $this->assertTrue($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 01:00:00'));
        $this->assertFalse($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 12:00:00'));
    }

    public function test_toggle_off_shelf_affects_availability(): void
    {
        $sku = $this->createSku();
        $this->menuService->publish(self::STORE_A, $sku->id);

        $off = $this->menuService->toggle(self::STORE_A, $sku->id);
        $this->assertSame(StoreMenuItem::STATUS_OFF, $off->status);
        $this->assertFalse($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 12:00:00'));
        $this->assertSame('off_shelf', $this->menuService->availability(self::STORE_A, $sku->id, '2026-10-26 12:00:00')['reason']);

        $on = $this->menuService->toggle(self::STORE_A, $sku->id);
        $this->assertSame(StoreMenuItem::STATUS_ON, $on->status);
        $this->assertTrue($this->menuService->isAvailable(self::STORE_A, $sku->id, '2026-10-26 12:00:00'));
    }

    public function test_platform_inactive_sku_is_unavailable(): void
    {
        $sku = $this->createSku('18.00', ProductSku::STATUS_DISABLED);
        $this->menuService->publish(self::STORE_A, $sku->id);

        $availability = $this->menuService->availability(self::STORE_A, $sku->id, '2026-10-26 12:00:00');

        $this->assertFalse($availability['available']);
        $this->assertSame('sku_inactive', $availability['reason']);
    }

    public function test_unpublished_item_is_unavailable(): void
    {
        $sku = $this->createSku();

        $availability = $this->menuService->availability(self::STORE_A, $sku->id, '2026-10-26 12:00:00');

        $this->assertFalse($availability['available']);
        $this->assertSame('not_published', $availability['reason']);
    }

    public function test_update_price_on_unpublished_item_throws(): void
    {
        $sku = $this->createSku();

        $this->expectException(MenuItemNotPublishedException::class);

        $this->menuService->updatePrice(self::STORE_A, $sku->id, 10);
    }

    public function test_invalid_status_rejected(): void
    {
        $sku = $this->createSku();

        $this->expectException(InvalidArgumentException::class);

        $this->menuService->publish(self::STORE_A, $sku->id, ['status' => 'paused']);
    }

    public function test_availability_list_only_returns_currently_available_items(): void
    {
        $available = $this->createSku('18.00');
        $offShelf = $this->createSku('16.00');
        $soldOut = $this->createSku('20.00');
        $outsideWindow = $this->createSku('12.00');

        $this->menuService->publish(self::STORE_A, $available->id);
        $this->menuService->publish(self::STORE_A, $offShelf->id);
        $this->menuService->publish(self::STORE_A, $soldOut->id);
        $this->menuService->publish(self::STORE_A, $outsideWindow->id, [
            'available_from' => '22:00',
            'available_to' => '23:00',
        ]);

        $this->menuService->toggle(self::STORE_A, $offShelf->id);
        $this->menuService->setSoldOut(self::STORE_A, $soldOut->id);

        $list = $this->menuService->availabilityList(self::STORE_A, '2026-10-26 12:00:00');

        $this->assertCount(1, $list);
        $this->assertSame($available->id, $list->first()->product_sku_id);
    }
}
