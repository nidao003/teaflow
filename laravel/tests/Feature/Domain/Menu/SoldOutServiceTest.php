<?php

namespace Tests\Feature\Domain\Menu;

use App\Domain\Menu\Models\SoldOutLog;
use App\Domain\Menu\Services\SoldOutService;
use App\Domain\Menu\Services\StoreMenuService;
use InvalidArgumentException;

class SoldOutServiceTest extends MenuTestCase
{
    private const STORE_A = 1;

    private const STORE_B = 2;

    private SoldOutService $soldOutService;

    private StoreMenuService $storeMenuService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->soldOutService = app(SoldOutService::class);
        $this->storeMenuService = app(StoreMenuService::class);
    }

    public function test_set_sold_out_records_log(): void
    {
        $sku = $this->createSku();
        $this->storeMenuService->publish(self::STORE_A, $sku->id);

        $item = $this->storeMenuService->setSoldOut(self::STORE_A, $sku->id, 9, '鲜奶用完');

        $this->assertTrue($item->sold_out);
        $this->assertNotNull($item->sold_out_at);
        $this->assertDatabaseHas('sold_out_logs', [
            'store_id' => self::STORE_A,
            'product_sku_id' => $sku->id,
            'action' => SoldOutLog::ACTION_SOLD_OUT,
            'operator_id' => 9,
            'note' => '鲜奶用完',
        ]);
    }

    public function test_restore_records_log(): void
    {
        $sku = $this->createSku();
        $this->storeMenuService->publish(self::STORE_A, $sku->id);
        $this->storeMenuService->setSoldOut(self::STORE_A, $sku->id);
        $this->storeMenuService->restore(self::STORE_A, $sku->id, 7, '原料到货');

        $this->assertSame(2, SoldOutLog::query()->where('store_id', self::STORE_A)->count());
        $this->assertDatabaseHas('sold_out_logs', [
            'store_id' => self::STORE_A,
            'product_sku_id' => $sku->id,
            'action' => SoldOutLog::ACTION_RESTORED,
            'operator_id' => 7,
            'note' => '原料到货',
        ]);
    }

    public function test_repeated_same_action_does_not_duplicate_log(): void
    {
        $sku = $this->createSku();
        $this->storeMenuService->publish(self::STORE_A, $sku->id);

        $this->storeMenuService->setSoldOut(self::STORE_A, $sku->id);
        $this->storeMenuService->setSoldOut(self::STORE_A, $sku->id);
        $this->assertSame(1, SoldOutLog::query()->count());

        $this->storeMenuService->restore(self::STORE_A, $sku->id);
        $this->storeMenuService->restore(self::STORE_A, $sku->id);
        $this->assertSame(2, SoldOutLog::query()->count());
    }

    public function test_sold_out_items_queried_by_store(): void
    {
        $skuA = $this->createSku();
        $skuB = $this->createSku();

        $this->storeMenuService->publish(self::STORE_A, $skuA->id);
        $this->storeMenuService->publish(self::STORE_A, $skuB->id);
        $this->storeMenuService->publish(self::STORE_B, $skuA->id);

        $this->storeMenuService->setSoldOut(self::STORE_A, $skuA->id);
        $this->storeMenuService->setSoldOut(self::STORE_A, $skuB->id);

        $storeAItems = $this->soldOutService->soldOutItems(self::STORE_A);
        $storeBItems = $this->soldOutService->soldOutItems(self::STORE_B);

        $this->assertCount(2, $storeAItems);
        $this->assertCount(0, $storeBItems);
    }

    public function test_logs_for_store_filters_and_orders(): void
    {
        $skuA = $this->createSku();
        $skuB = $this->createSku();

        $this->storeMenuService->publish(self::STORE_A, $skuA->id);
        $this->storeMenuService->publish(self::STORE_A, $skuB->id);
        $this->storeMenuService->publish(self::STORE_B, $skuA->id);

        $this->storeMenuService->setSoldOut(self::STORE_A, $skuA->id, null, 'A 店售罄');
        $this->storeMenuService->setSoldOut(self::STORE_A, $skuB->id, null, 'A 店售罄 2');
        $this->storeMenuService->setSoldOut(self::STORE_B, $skuA->id, null, 'B 店售罄');

        $logs = $this->soldOutService->logsForStore(self::STORE_A);

        $this->assertCount(2, $logs);
        $this->assertSame(self::STORE_A, $logs->first()->store_id);
    }

    public function test_record_rejects_invalid_action(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->soldOutService->record(1, 1, 'unknown');
    }
}
