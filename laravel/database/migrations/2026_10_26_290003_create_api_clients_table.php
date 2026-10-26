<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 开放 API 客户端（第三方应用）。
 *
 * secret 只存 hash（sha256），明文仅在签发时返回一次；校验用 hash_equals 常量时间比较。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->comment('客户端名称');
            $table->string('client_id', 64)->comment('客户端标识，全局唯一');
            $table->string('secret_hash', 64)->comment('客户端密钥 sha256 摘要');
            $table->string('status', 20)->default('active')->comment('状态：active/disabled');
            $table->timestamp('last_used_at')->nullable()->comment('最近一次校验通过时间');
            $table->timestamps();

            $table->unique('client_id', 'api_clients_client_id_unique');
            $table->index('status', 'api_clients_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_clients');
    }
};
