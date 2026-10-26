<?php

namespace App\Domain\Printing\Exceptions;

use RuntimeException;

/**
 * ESC/POS 驱动层打印失败（连接/写入/指令错误）。
 */
class PrintDriverException extends RuntimeException {}
