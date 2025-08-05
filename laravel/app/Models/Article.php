<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Article extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'articles';
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    protected $fillable = ['title','body','type_id','sort'];
    protected $appends = ['type_title'];
    public function type()
    {
        return $this->belongsTo(ArticleType::class, 'type_id', 'id');
    }
    public function getTypeTitleAttribute()
    {
        return $this->type->title;
    }

}
