<?php

namespace App\Domain\Product\Exceptions;

use RuntimeException;

/**
 * 无法确定原料平均单价：stock_items 无记录、未传入 fallback、历史成本卡也没有该原料。
 */
class MaterialCostUnavailableException extends RuntimeException {}
