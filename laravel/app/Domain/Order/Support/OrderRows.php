<?php

declare(strict_types=1);

namespace App\Domain\Order\Support;

use Illuminate\Support\Facades\DB;

/**
 * 现有 orders 表的只读访问辅助。
 *
 * 订单域不改 orders 表结构：读取用查询构造器，状态/金额更新统一走
 * DB::table('orders') 的 update（避免与现有 App\Models\Order 产生耦合）。
 */
final class OrderRows
{
    /**
     * 按订单号读取订单行。
     *
     * @return array<string, mixed>|null
     */
    public static function findByOrderNumber(string $orderNumber, bool $lockForUpdate = false): ?array
    {
        $query = DB::table('orders')->where('order_number', $orderNumber);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $row = $query->first();

        return $row === null ? null : (array) $row;
    }

    /**
     * 读取订单当前状态。
     */
    public static function status(string $orderNumber): ?string
    {
        $status = DB::table('orders')->where('order_number', $orderNumber)->value('order_status');

        return $status === null ? null : (string) $status;
    }
}
