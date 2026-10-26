<?php

namespace App\Domain\Product\Exceptions;

use RuntimeException;

/**
 * 指定 SKU 在指定日期没有生效中的配方版本。
 */
class NoActiveRecipeException extends RuntimeException {}
