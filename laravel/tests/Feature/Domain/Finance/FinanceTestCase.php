<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Finance;

use App\Domain\Finance\Services\CostService;
use App\Domain\Finance\Services\DailyCloseService;
use App\Domain\Finance\Services\ProfitService;
use App\Domain\Finance\Services\ReconciliationService;
use App\Domain\Finance\Services\RevenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 财务/经营分析域测试基类。
 *
 * 默认强制 sqlite 内存库；若外部显式提供 DB_CONNECTION 则尊重外部配置。
 * 源数据（orders/payments/refunds）统一用 DB::table 造，不依赖其他 Domain 的 Service。
 */
abstract class FinanceTestCase extends TestCase
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

    protected function revenueService(): RevenueService
    {
        return app(RevenueService::class);
    }

    protected function costService(): CostService
    {
        return app(CostService::class);
    }

    protected function profitService(): ProfitService
    {
        return app(ProfitService::class);
    }

    protected function reconciliationService(): ReconciliationService
    {
        return app(ReconciliationService::class);
    }

    protected function dailyCloseService(): DailyCloseService
    {
        return app(DailyCloseService::class);
    }

    /**
     * 造订单（最小必填字段；金额为元）。
     */
    protected function makeOrder(
        string $orderNumber,
        int $storeId,
        float $totalAmount,
        float $paidAmount,
        string $createdAt,
        ?string $paidAt = null,
        string $orderStatus = 'finished'
    ): void {
        DB::table('orders')->insert([
            'order_number' => $orderNumber,
            'get_code' => 'G'.substr(md5($orderNumber), 0, 4),
            'seller_id' => 1,
            'store_id' => $storeId,
            'user_id' => 1,
            'delivery_type' => 'self-pickup',
            'order_status' => $orderStatus,
            'pay_status' => 'paid',
            'delivery_status' => 'finished',
            'payment_method' => 'wechat',
            'delivery_method' => 'pickup',
            'paid_at' => $paidAt,
            'total_quantity' => 1,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'real_amount' => $paidAmount,
            'refunded_amount' => 0,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    /**
     * 造支付单。
     */
    protected function makePayment(
        string $orderNumber,
        int $storeId,
        string $channel,
        float $amount,
        string $paidAt,
        string $status = 'success'
    ): void {
        DB::table('payments')->insert([
            'order_number' => $orderNumber,
            'store_id' => $storeId,
            'channel' => $channel,
            'out_trade_no' => 'OTN-'.uniqid('', true),
            'amount' => $amount,
            'status' => $status,
            'paid_at' => $paidAt,
            'idempotency_key' => 'IK-'.uniqid('', true),
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);
    }

    /**
     * 造退款单。
     */
    protected function makeRefund(
        string $orderNumber,
        float $amount,
        string $refundedAt,
        string $status = 'success'
    ): void {
        DB::table('refunds')->insert([
            'order_number' => $orderNumber,
            'refund_no' => 'RFN-'.uniqid('', true),
            'amount' => $amount,
            'reason' => '测试退款',
            'status' => $status,
            'refunded_at' => $refundedAt,
            'created_at' => $refundedAt,
            'updated_at' => $refundedAt,
        ]);
    }
}
