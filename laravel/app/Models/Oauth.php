<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Oauth extends Model
{
    use HasFactory;
    protected $table = 'oauth';
    protected $guarded = [];
    protected $hidden = ['access_token', 'refresh_token'];
    public $timestamps = true;
    protected $fillable = [
        'openid',
        'type',
        'name',
        'country_code',
        'phone',
        'pure_phone',
        'unionid',
        'access_token',
        'expires_in',
        'refresh_token',
        'user_id',
        'created_at',
        'updated_at',
    ];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
    /**
     * 写入数据成功后，如果有phone值，向User表写入数据
     */
    public static function boot()
    {
        parent::boot();
        static::created(function ($model) {
            if ($model->phone) {
                $model->updateOrCreateUser($model);
            }
        });
        static::updated(function ($model) {
            if ($model->phone) {
                $model->updateOrCreateUser($model);
            }
        });
    }
    protected function updateOrCreateUser($model)
    {
        $user = User::updateOrCreate(
            ['phone' => $model->phone], // 查找条件
            ['phone' => $model->phone] // 创建或更新时的字段值
        );
        if (!$this->user_id || $user->id != $this->user_id) {
            $this->user_id = $user->id;
            $this->save(); // 保存更新后的 user_id
        }
    }

}
