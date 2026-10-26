<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->unique()->comment('关联 users.id，可空');
            $table->string('phone_hash', 64)->nullable()->comment('手机号哈希（不存明文）');
            $table->unsignedBigInteger('level_id')->nullable()->comment('当前会员等级 member_levels.id');
            $table->integer('points_balance')->default(0)->comment('积分余额（冗余汇总）');
            $table->decimal('growth', 12, 2)->default(0)->comment('成长值');
            $table->unsignedTinyInteger('status')->default(1)->comment('1 启用 0 停用');
            $table->timestamp('joined_at')->nullable()->comment('入会时间');
            $table->timestamps();

            $table->index('phone_hash');
            $table->index('level_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
