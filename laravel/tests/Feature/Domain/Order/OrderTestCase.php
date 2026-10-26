<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Order;

use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Enums\PayStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 订单/支付/退款域测试基类。
 *
 * 现有 orders 表结构不改：测试用 DB::table 直接造订单（只读/DB 更新），
 * 不依赖现有订单控制器，也不修改现有 Model/Controller。
 */
abstract class OrderTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * 造一笔待支付订单。
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function makeOrder(array $attributes = []): array
    {
        $orderNumber = $attributes['order_number'] ?? 'ORD-'.strtoupper(uniqid());

        DB::table('orders')->insert(array_merge([
            'order_number' => $orderNumber,
            'get_code' => 'A'.random_int(100, 999),
            'seller_id' => 1,
            'store_id' => 1,
            'user_id' => 1,
            'delivery_type' => 'self-pickup',
            'order_status' => OrderStatus::PENDING_PAYMENT->value,
            'pay_status' => PayStatus::PENDING->value,
            'delivery_status' => 'pending',
            'payment_method' => 'wechat',
            'delivery_method' => 'self-pickup',
            'total_quantity' => 2,
            'total_mix_amount' => '0.00',
            'total_amount' => '30.00',
            'paid_amount' => '0.00',
            'real_amount' => '30.00',
            'refunded_amount' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));

        return $this->orderRow($orderNumber);
    }

    /**
     * 造一笔已支付订单（退款用例）。
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function makePaidOrder(string $amount = '100.00', array $attributes = []): array
    {
        return $this->makeOrder(array_merge([
            'order_status' => OrderStatus::PAID->value,
            'pay_status' => PayStatus::COMPLETED->value,
            'paid_at' => now(),
            'total_amount' => $amount,
            'paid_amount' => $amount,
            'real_amount' => $amount,
            'refunded_amount' => '0.00',
        ], $attributes));
    }

    /**
     * 读取订单行（DB::table，避免与现有 App\Models\Order 耦合）。
     *
     * @return array<string, mixed>
     */
    protected function orderRow(string $orderNumber): array
    {
        $row = DB::table('orders')->where('order_number', $orderNumber)->first();
        $this->assertNotNull($row, '订单不存在：'.$orderNumber);

        return (array) $row;
    }

    protected function assertAmount(float $expected, mixed $actual, string $message = ''): void
    {
        $this->assertSame($expected, round((float) $actual, 2), $message);
    }
}
