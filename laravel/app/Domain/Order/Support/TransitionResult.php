<?php

declare(strict_types=1);

namespace App\Domain\Order\Support;

use App\Domain\Order\Models\OrderEvent;

/**
 * 状态机单次迁移的内部结果。
 *
 * 非法迁移需要在「事务提交后」再抛异常，才能保证 illegal_transition 审计事件不随
 * 事务回滚丢失；因此用该值对象把结果带出事务闭包。
 *
 * @internal
 */
final class TransitionResult
{
    public function __construct(
        public readonly bool $illegal,
        public readonly string $from,
        public readonly string $to,
        public readonly OrderEvent $event,
    ) {}
}
