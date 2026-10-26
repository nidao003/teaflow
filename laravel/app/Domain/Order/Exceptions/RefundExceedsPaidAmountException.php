<?php

declare(strict_types=1);

namespace App\Domain\Order\Exceptions;

/**
 * 累计退款金额超过订单实付金额。
 */
class RefundExceedsPaidAmountException extends OrderException
{
    /**
     * @param  string  $paid  实付金额（格式化后的两位小数）
     * @param  string  $committed  已退款 + 处理中金额
     * @param  string  $requested  本次申请金额
     */
    public static function forOrder(string $orderNumber, string $paid, string $committed, string $requested): self
    {
        return new self(sprintf(
            '退款金额超过实付金额：订单 %s 实付 %s，已退款(含处理中) %s，本次申请 %s',
            $orderNumber,
            $paid,
            $committed,
            $requested
        ));
    }
}
