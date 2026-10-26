<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 会员价规则：按「商品 SKU + 会员等级 + 生效日期」配置的会员专享价。
 *
 * effective_from / effective_to 以 Y-m-d 字符串存储（不做 date cast），
 * 空值分别表示「立即生效」「长期有效」。
 *
 * @property int $id
 * @property int $product_sku_id
 * @property int $member_level_id
 * @property string $price
 * @property string|null $effective_from
 * @property string|null $effective_to
 * @property int $status
 */
class MemberPriceRule extends Model
{
    protected $table = 'member_price_rules';

    protected $fillable = [
        'product_sku_id',
        'member_level_id',
        'price',
        'effective_from',
        'effective_to',
        'status',
    ];

    protected $casts = [
        'product_sku_id' => 'integer',
        'member_level_id' => 'integer',
        'price' => 'decimal:2',
        'status' => 'integer',
    ];
}
