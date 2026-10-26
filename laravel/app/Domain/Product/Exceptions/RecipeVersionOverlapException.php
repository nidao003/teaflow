<?php

namespace App\Domain\Product\Exceptions;

use RuntimeException;

/**
 * 同一 SKU 的配方版本生效日期区间发生重叠。
 */
class RecipeVersionOverlapException extends RuntimeException {}
