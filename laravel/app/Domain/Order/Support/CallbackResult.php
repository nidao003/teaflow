<?php

declare(strict_types=1);

namespace App\Domain\Order\Support;

use App\Domain\Order\Models\PaymentCallback;

/**
 * 支付回调处理的内部结果。
 *
 * 与状态机同理：回调审计记录需要在事务提交后再抛业务异常，避免留痕被回滚。
 *
 * @internal
 */
final class CallbackResult
{
    /**
     * @param  array<string, string>  $detail
     */
    public function __construct(
        public readonly PaymentCallback $callback,
        public readonly ?string $error = null,
        public readonly array $detail = [],
    ) {}
}
