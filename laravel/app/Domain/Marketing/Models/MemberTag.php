<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 会员标签。
 *
 * @property int $id
 * @property string $name
 * @property string $color
 * @property int $status
 */
class MemberTag extends Model
{
    protected $table = 'member_tags';

    protected $fillable = [
        'name',
        'color',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];
}
