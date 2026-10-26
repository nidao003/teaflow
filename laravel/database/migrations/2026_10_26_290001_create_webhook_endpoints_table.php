<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook 订阅端点。
 *
 * - secret 用 Laravel encrypted cast 存储（DB 中为密文，不落明文），发送时解密后做 HMAC 签名；
 * - events 为订阅事件数组（JSON），如 ["order.created","order.paid"]；
 * - status 用字符串常量 active/disabled。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('端点名称');
            $table->string('url', 500)->comment('回调地址');
            $table->text('secret')->comment('签名密钥（encrypted cast 密文存储）');
            $table->json('events')->comment('订阅事件列表 JSON');
            $table->string('status', 20)->default('active')->comment('状态：active/disabled');
            $table->timestamp('last_success_at')->nullable()->comment('最近一次投递成功时间');
            $table->timestamp('last_failure_at')->nullable()->comment('最近一次投递失败时间');
            $table->timestamps();

            $table->index('status', 'webhook_endpoints_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
