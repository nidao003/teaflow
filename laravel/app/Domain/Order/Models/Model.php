<?php

declare(strict_types=1);

namespace App\Domain\Order\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * 订单域模型基类：字段白名单由迁移与 Service 层负责。
 */
abstract class Model extends EloquentModel
{
    protected $guarded = [];
}
