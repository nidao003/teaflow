<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Finance;

use App\Domain\Finance\Enums\ReconciliationStatus;
use App\Domain\Finance\Models\ReconciliationDaily;

class ReconciliationServiceTest extends FinanceTestCase
{
    private function seedWechatPayment(float $amount, string $day = '2026-10-20'): void
    {
        $this->makeOrder('ORD-REC', 1, $amount, $amount, $day.' 09:00:00', $day.' 09:05:00');
        $this->makePayment('ORD-REC', 1, 'wechat', $amount, $day.' 09:05:00');
    }

    public function test_system_amount_aggregates_successful_payments_by_channel(): void
    {
        $day = '2026-10-20';
        $this->makeOrder('ORD-WX', 1, 100.00, 100.00, $day.' 09:00:00', $day.' 09:05:00');
        $this->makePayment('ORD-WX', 1, 'wechat', 100.00, $day.' 09:05:00');
        $this->makePayment('ORD-WX', 1, 'wechat', 30.00, $day.' 09:06:00', 'failed');

        $this->makeOrder('ORD-ALI', 1, 50.00, 50.00, $day.' 10:00:00', $day.' 10:05:00');
        $this->makePayment('ORD-ALI', 1, 'alipay', 50.00, $day.' 10:05:00');

        $this->assertSame(100.0, $this->reconciliationService()->systemAmount(1, $day, 'wechat'));
        $this->assertSame(50.0, $this->reconciliationService()->systemAmount(1, $day, 'alipay'));
    }

    public function test_diff_within_threshold_is_matched(): void
    {
        $day = '2026-10-20';
        $this->seedWechatPayment(100.00, $day);

        $record = $this->reconciliationService()->record(1, $day, 'wechat', [
            'channel_amount' => 100.50,
            'threshold' => 1.00,
        ]);

        $this->assertSame(100.0, (float) $record->system_amount);
        $this->assertSame(100.5, (float) $record->channel_amount);
        $this->assertSame(0.5, (float) $record->diff);
        $this->assertSame(ReconciliationStatus::MATCHED, $record->status);
    }

    public function test_diff_over_threshold_is_exception(): void
    {
        $day = '2026-10-20';
        $this->seedWechatPayment(100.00, $day);

        $record = $this->reconciliationService()->record(1, $day, 'wechat', [
            'channel_amount' => 105.00,
            'threshold' => 1.00,
        ]);

        $this->assertSame(5.0, (float) $record->diff);
        $this->assertSame(ReconciliationStatus::EXCEPTION, $record->status);
    }

    public function test_default_threshold_is_one_cent(): void
    {
        $day = '2026-10-20';
        $this->seedWechatPayment(100.00, $day);

        $matched = $this->reconciliationService()->record(1, $day, 'wechat', ['channel_amount' => 100.01]);
        $this->assertSame(ReconciliationStatus::MATCHED, $matched->status);

        $exception = $this->reconciliationService()->record(1, $day, 'wechat', ['channel_amount' => 100.02]);
        $this->assertSame(ReconciliationStatus::EXCEPTION, $exception->status);
    }

    public function test_missing_channel_amount_stays_pending(): void
    {
        $day = '2026-10-20';
        $this->seedWechatPayment(100.00, $day);

        $record = $this->reconciliationService()->record(1, $day, 'wechat');

        $this->assertSame(ReconciliationStatus::PENDING, $record->status);
        $this->assertSame(100.0, (float) $record->system_amount);
        $this->assertSame(0.0, (float) $record->channel_amount);
        $this->assertSame(0.0, (float) $record->diff);
    }

    public function test_manual_system_amount_overrides_aggregation(): void
    {
        $day = '2026-10-20';
        $this->seedWechatPayment(100.00, $day);

        $record = $this->reconciliationService()->record(1, $day, 'wechat', [
            'system_amount' => 90.00,
            'channel_amount' => 100.00,
            'threshold' => 0.00,
        ]);

        $this->assertSame(90.0, (float) $record->system_amount);
        $this->assertSame(10.0, (float) $record->diff);
        $this->assertSame(ReconciliationStatus::EXCEPTION, $record->status);
    }

    public function test_record_upserts_single_row_per_store_day_channel(): void
    {
        $day = '2026-10-20';
        $this->seedWechatPayment(100.00, $day);

        $first = $this->reconciliationService()->record(1, $day, 'wechat', ['channel_amount' => 100.00]);
        $second = $this->reconciliationService()->record(1, $day, 'WECHAT', ['channel_amount' => 120.00, 'threshold' => 1.00]);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, ReconciliationDaily::query()->count());
        $this->assertSame(20.0, (float) $second->diff);
        $this->assertSame(ReconciliationStatus::EXCEPTION, $second->status);
        $this->assertSame(1, $this->reconciliationService()->listForDay(1, $day)->count());
    }

    public function test_empty_channel_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->reconciliationService()->record(1, '2026-10-20', '   ');
    }
}
