<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('oauth', function (Blueprint $table) {
            $table->id();
            $table->string('openid', 200)->comment('openid');
            $table->string('type', 50)->default('weixin')->comment('类型');
            $table->string('name')->nullable();
            $table->string('country_code')->nullable()->comment('区号');
            $table->string('phone')->nullable()->comment('用户绑定的手机号（国外手机号会有区号）');
            //备注
            $table->string('pure_phone')->nullable()->comment('没有区号的手机号');
            $table->string('unionid')->nullable()->comment('unionid');
            $table->string('access_token', 255)->nullable();
            $table->integer('expires_in')->nullable()->comment('token过期时间');
            $table->string('refresh_token', 255)->nullable();
            //用户id
            $table->unsignedBigInteger('user_id')->nullable();
            //创建时间
            $table->timestamp('created_at')->nullable();
            //更新时间
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('oauth');
    }
};
