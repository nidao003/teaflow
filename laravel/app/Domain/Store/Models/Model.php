<?php

namespace App\Domain\Store\Models;

use Illuminate\Database\Eloquent\Model as EloquentModel;

/**
 * 区域/门店域模型基类：内部域，字段白名单由迁移与 Service 层负责。
 */
abstract class Model extends EloquentModel
{
    protected $guarded = [];
}
