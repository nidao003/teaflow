<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Hr;

use App\Domain\Hr\Models\Target;
use App\Domain\Hr\Services\TargetService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TargetServiceTest extends HrTestCase
{
    private TargetService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(TargetService::class);
    }

    public function test_progress_attainment_rate_and_gap(): void
    {
        $this->service->setTarget(self::STORE_ID, '2026-10', 'revenue', '1000.00');
        $this->service->recordActual(self::STORE_ID, '2026-10', 'revenue', '850.00');

        $progress = $this->service->progress(self::STORE_ID, '2026-10', 'revenue');

        $this->assertSame('1000.00', $progress['target_value']);
        $this->assertSame('850.00', $progress['actual_value']);
        $this->assertSame('85.00', $progress['attainment_rate']);
        $this->assertSame('150.00', $progress['gap']);
        $this->assertFalse($progress['achieved']);

        $this->service->recordActual(self::STORE_ID, '2026-10', 'revenue', '1200.00');
        $progress = $this->service->progress(self::STORE_ID, '2026-10', 'revenue');

        $this->assertSame('120.00', $progress['attainment_rate']);
        $this->assertSame('0.00', $progress['gap']);
        $this->assertTrue($progress['achieved']);
    }

    public function test_set_target_upserts_by_store_period_metric(): void
    {
        $first = $this->service->setTarget(self::STORE_ID, '2026-10', 'orders', '3000.00');
        $second = $this->service->setTarget(self::STORE_ID, '2026-10', 'orders', '3500.00');

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, Target::query()->count());
        $this->assertSame('3500.00', $second->refresh()->target_value);
    }

    public function test_target_value_must_be_positive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->setTarget(self::STORE_ID, '2026-10', 'revenue', '0.00');
    }

    public function test_actual_value_must_not_be_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->setTarget(self::STORE_ID, '2026-10', 'revenue', '1000.00', '-1.00');
    }

    public function test_record_actual_requires_existing_target(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->service->recordActual(self::STORE_ID, '2026-10', 'missing', '100.00');
    }
}
