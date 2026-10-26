<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Fulfillment;

use App\Domain\Fulfillment\Models\Pickup;
use App\Domain\Fulfillment\Models\ReservationSlot;
use App\Domain\Fulfillment\Models\VerifyCode;
use App\Domain\Fulfillment\Services\PickupService;
use App\Domain\Fulfillment\Services\ReservationService;
use App\Domain\Fulfillment\Services\VerifyCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 核销/自提/预约域测试基类。
 *
 * 默认强制 sqlite 内存库；若外部（phpunit.xml / shell）显式提供 DB_CONNECTION 则尊重外部配置。
 */
abstract class FulfillmentTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->configureTestingDatabase();
        parent::setUp();
    }

    private function configureTestingDatabase(): void
    {
        $explicit = $_ENV['DB_CONNECTION'] ?? $_SERVER['DB_CONNECTION'] ?? getenv('DB_CONNECTION');

        if (is_string($explicit) && $explicit !== '') {
            return;
        }

        $this->setEnvironmentValue('DB_CONNECTION', 'sqlite');
        $this->setEnvironmentValue('DB_DATABASE', ':memory:');
    }

    private function setEnvironmentValue(string $key, string $value): void
    {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    protected function verifyCodes(): VerifyCodeService
    {
        return app(VerifyCodeService::class);
    }

    protected function pickups(): PickupService
    {
        return app(PickupService::class);
    }

    protected function reservations(): ReservationService
    {
        return app(ReservationService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSlot(array $overrides = []): ReservationSlot
    {
        return $this->reservations()->createSlot(array_merge([
            'store_id' => 1,
            'date' => '2026-11-01',
            'start_time' => '18:00',
            'end_time' => '19:00',
            'capacity' => 10,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function issueCode(array $overrides = []): VerifyCode
    {
        return $this->verifyCodes()->issue(array_merge([
            'order_number' => 'ORDER-1',
            'store_id' => 1,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makePickup(array $overrides = []): Pickup
    {
        return $this->pickups()->create(array_merge([
            'order_number' => 'ORDER-P-1',
            'store_id' => 1,
        ], $overrides));
    }
}
