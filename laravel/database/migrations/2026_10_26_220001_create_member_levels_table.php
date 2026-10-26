<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique()->comment('等级名称');
            $table->decimal('threshold', 12, 2)->default(0)->comment('升级所需成长值门槛');
            $table->decimal('discount_rate', 5, 2)->default(1.00)->comment('折扣率，0.95 表示 95 折');
            $table->json('benefits_json')->nullable()->comment('等级权益配置');
            $table->unsignedTinyInteger('status')->default(1)->comment('1 启用 0 停用');
            $table->timestamps();

            $table->index('threshold');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_levels');
    }
};
