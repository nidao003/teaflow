<?php

namespace App\Domain\Printing\Exceptions;

use RuntimeException;

/**
 * 打印失败重试次数达到上限。
 */
class PrintRetryLimitExceededException extends RuntimeException {}
