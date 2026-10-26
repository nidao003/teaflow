<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Hr;

use App\Domain\Hr\Enums\InspectionItemResult;
use App\Domain\Hr\Enums\InspectionStatus;
use App\Domain\Hr\Exceptions\InspectionException;
use App\Domain\Hr\Models\Inspection;
use App\Domain\Hr\Services\InspectionService;

class InspectionServiceTest extends HrTestCase
{
    private InspectionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(InspectionService::class);
    }

    public function test_submit_requires_all_items_scored(): void
    {
        $inspection = $this->createInspection(['地面清洁', '物料效期']);
        $items = $inspection->items()->orderBy('id')->get();

        $this->service->scoreItem(
            (int) $inspection->getKey(),
            (int) $items[0]->getKey(),
            InspectionItemResult::PASSED
        );

        try {
            $this->service->submit((int) $inspection->getKey());
            $this->fail('存在未评分检查项时不允许提交');
        } catch (InspectionException $e) {
            $this->assertStringContainsString('未评分', $e->getMessage());
        }

        $this->assertSame(InspectionStatus::DRAFT, $inspection->refresh()->status);
        $this->assertSame('0.00', $inspection->refresh()->score);
    }

    public function test_failed_inspection_computes_score(): void
    {
        $inspection = $this->createInspection(['地面清洁', '物料效期', '员工着装', '设备温度']);
        $items = $inspection->items()->orderBy('id')->get();

        $this->service->scoreItem((int) $inspection->getKey(), (int) $items[0]->getKey(), InspectionItemResult::PASSED);
        $this->service->scoreItem((int) $inspection->getKey(), (int) $items[1]->getKey(), InspectionItemResult::PASSED);
        $this->service->scoreItem((int) $inspection->getKey(), (int) $items[2]->getKey(), InspectionItemResult::PASSED);
        $this->service->scoreItem((int) $inspection->getKey(), (int) $items[3]->getKey(), InspectionItemResult::FAILED, '冷藏温度偏高');

        $result = $this->service->submit((int) $inspection->getKey());

        $this->assertSame('75.00', $result->score);
        $this->assertSame(InspectionStatus::FAILED, $result->status);
        $this->assertNotNull($result->checked_at);
    }

    public function test_passed_inspection_and_na_items_are_excluded(): void
    {
        $inspection = $this->createInspection(['地面清洁', '物料效期', '设备温度']);
        $items = $inspection->items()->orderBy('id')->get();

        $this->service->scoreItem((int) $inspection->getKey(), (int) $items[0]->getKey(), InspectionItemResult::PASSED);
        $this->service->scoreItem((int) $inspection->getKey(), (int) $items[1]->getKey(), InspectionItemResult::PASSED);
        $this->service->scoreItem((int) $inspection->getKey(), (int) $items[2]->getKey(), InspectionItemResult::NA);

        $result = $this->service->submit((int) $inspection->getKey());

        $this->assertSame('100.00', $result->score);
        $this->assertSame(InspectionStatus::PASSED, $result->status);
    }

    public function test_rectify_then_close_flow(): void
    {
        $inspection = $this->createInspection(['地面清洁', '物料效期']);
        $items = $inspection->items()->orderBy('id')->get();

        $this->service->scoreItem((int) $inspection->getKey(), (int) $items[0]->getKey(), InspectionItemResult::PASSED);
        $this->service->scoreItem((int) $inspection->getKey(), (int) $items[1]->getKey(), InspectionItemResult::FAILED);
        $this->service->submit((int) $inspection->getKey());

        $this->expectException(InspectionException::class);
        $this->service->close((int) $inspection->getKey());
    }

    public function test_rectifying_then_closed(): void
    {
        $inspection = $this->createInspection(['地面清洁']);
        $item = $inspection->items()->orderBy('id')->firstOrFail();

        $this->service->scoreItem((int) $inspection->getKey(), (int) $item->getKey(), InspectionItemResult::FAILED);
        $this->service->submit((int) $inspection->getKey());

        $rectifying = $this->service->rectify((int) $inspection->getKey());
        $this->assertSame(InspectionStatus::RECTIFYING, $rectifying->status);

        $closed = $this->service->close((int) $inspection->getKey());
        $this->assertSame(InspectionStatus::CLOSED, $closed->status);
    }

    public function test_duplicate_submit_is_rejected(): void
    {
        $inspection = $this->createInspection(['地面清洁']);
        $item = $inspection->items()->orderBy('id')->firstOrFail();
        $this->service->scoreItem((int) $inspection->getKey(), (int) $item->getKey(), InspectionItemResult::PASSED);

        $this->service->submit((int) $inspection->getKey());

        $this->expectException(InspectionException::class);
        $this->service->submit((int) $inspection->getKey());
    }

    public function test_create_inspection_requires_items(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->createInspection([
            'store_id' => self::STORE_ID,
            'template_name' => '空模板',
            'items' => [],
        ]);
    }

    /**
     * @param  list<string>  $items
     */
    private function createInspection(array $items): Inspection
    {
        return $this->service->createInspection([
            'store_id' => self::STORE_ID,
            'template_name' => '门店周巡检',
            'inspector_id' => null,
            'items' => array_map(static fn (string $item): array => ['item' => $item], $items),
        ]);
    }
}
