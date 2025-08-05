<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArticleType extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $table = 'article_types';
    public $timestamps = false;
    protected $fillable = [
        'title',
    ];
    public function articles()
    {
        return $this->hasMany(Article::class, 'type_id', 'id');
    }
    /**
     * 文章数量属性
     */
    public function getArticleCountAttribute()
    {
        return  $this->articles()->count();
    }
}
