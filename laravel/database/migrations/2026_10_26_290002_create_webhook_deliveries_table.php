<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook 投递记录（每次发送一条，重试复用同一条并累加 attempts）。
 *
 * status：pending（待投递/待重试）、success、failed（达到最大重试次数）。
 * next_retry_at：失败且还能重试时的下次可重试时间；成功或最终失败时清空。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('endpoint_id')->comment('webhook_endpoints.id，逻辑外键');
            $table->string('event', 100)->comment('事件名，如 order.created');
            $table->json('payload')->comment('投递 payload JSON');
            $table->string('status', 20)->default('pending')->comment('状态：pending/success/failed');
            $table->unsignedInteger('attempts')->default(0)->comment('已尝试次数');
            $table->unsignedSmallInteger('response_code')->nullable()->comment('HTTP 响应码');
            $table->text('error')->nullable()->comment('失败原因');
            $table->timestamp('next_retry_at')->nullable()->comment('下次可重试时间');
            $table->timestamps();

            $table->index(['endpoint_id', 'status'], 'webhook_deliveries_endpoint_status_idx');
            $table->index('next_retry_at', 'webhook_deliveries_next_retry_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
    }
};
