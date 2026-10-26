<?php

namespace App\Domain\Printing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 打印模板：门店级（store_id 非空）或通用（store_id 为空），content 使用 {{var}} 占位。
 */
class PrintTemplate extends Model
{
    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_LABEL = 'label';

    public const TYPE_PRODUCTION = 'production';

    public const TYPES = [self::TYPE_RECEIPT, self::TYPE_LABEL, self::TYPE_PRODUCTION];

    public const STATUS_ACTIVE = 1;

    public const STATUS_DISABLED = -1;

    protected $table = 'print_templates';

    protected $fillable = [
        'store_id',
        'type',
        'name',
        'content',
        'width',
        'status',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'width' => 'integer',
        'status' => 'integer',
    ];
}
