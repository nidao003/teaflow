<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_points_rules', function (Blueprint $table) {
            $table->id();
            $table->string('rule_type', 16)->comment('earn 获得 / expire 过期');
            $table->decimal('ratio', 10, 2)->default(1.00)->comment('earn：每元获得积分；expire：有效期天数');
            $table->string('scope', 32)->default('all')->comment('规则适用范围');
            $table->unsignedTinyInteger('status')->default(1)->comment('1 启用 0 停用');
            $table->timestamps();

            $table->unique(['rule_type', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_points_rules');
    }
};
