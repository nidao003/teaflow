<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->comment('活动名称');
            $table->string('type', 32)->comment('full_reduction/percent/second_half/gift');
            $table->string('scope', 16)->default('all')->comment('all 全场 / store 指定门店');
            $table->unsignedBigInteger('store_id')->nullable()->comment('scope=store 时的门店 stores.id');
            $table->json('rules_json')->nullable()->comment('活动规则 JSON（门槛/折扣/次数/叠加等）');
            $table->timestamp('start_at')->nullable()->comment('开始时间，空表示不限');
            $table->timestamp('end_at')->nullable()->comment('结束时间，空表示不限');
            $table->string('status', 16)->default('draft')->comment('draft/active/disabled');
            $table->integer('priority')->default(0)->comment('优先级，越大越优先');
            $table->timestamps();

            $table->index(['status', 'start_at', 'end_at'], 'campaigns_status_window_idx');
            $table->index(['scope', 'store_id'], 'campaigns_scope_store_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
