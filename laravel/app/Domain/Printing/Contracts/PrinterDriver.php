<?php

namespace App\Domain\Printing\Contracts;

/**
 * 打印机驱动抽象：把渲染后的文本 payload 发送到具体打印设备。
 *
 * ESC/POS、云打印、Android 打印等实现同一接口；失败时抛异常，由 PrintService 记录状态/重试。
 */
interface PrinterDriver
{
    /**
     * @throws \RuntimeException 打印失败时抛出
     */
    public function print(string $payload): void;
}
