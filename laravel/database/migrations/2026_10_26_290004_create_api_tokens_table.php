<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 开放 API 访问令牌。
 *
 * token 只存 hash（sha256），明文仅在签发时返回一次；
 * abilities 形如 ["order.view","report.view"]，["*"] 表示全部权限；
 * revoked_at 非空或 expires_at 已过期的令牌一律拒绝。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('api_client_id')->comment('api_clients.id，逻辑外键');
            $table->string('token_hash', 64)->comment('令牌 sha256 摘要');
            $table->json('abilities')->comment('权限点列表 JSON');
            $table->timestamp('expires_at')->nullable()->comment('过期时间，空表示不过期');
            $table->timestamp('revoked_at')->nullable()->comment('吊销时间，非空即失效');
            $table->timestamps();

            $table->unique('token_hash', 'api_tokens_token_hash_unique');
            $table->index('api_client_id', 'api_tokens_client_idx');
            $table->index('expires_at', 'api_tokens_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_tokens');
    }
};
