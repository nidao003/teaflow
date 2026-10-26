<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 会员-标签关系（member_id 逻辑指向 members.id）。
 *
 * @property int $id
 * @property int $member_id
 * @property int $tag_id
 */
class MemberTagRelation extends Model
{
    protected $table = 'member_tag_relations';

    protected $fillable = [
        'member_id',
        'tag_id',
    ];

    protected $casts = [
        'member_id' => 'integer',
        'tag_id' => 'integer',
    ];
}
