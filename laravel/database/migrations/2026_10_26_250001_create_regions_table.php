<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 区域表：树形区域（大区 → 城市/片区），用物化路径 path 查子树。
 *
 * 约定：
 * - parent_id / tenant_id 是逻辑外键：只建索引，不建数据库外键约束；
 * - status 用字符串常量（active / disabled），不用数据库 enum；
 * - path 形如 "1/2/"，用于按前缀查后代，避免递归 SQL。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID，逻辑外键 regions.tenant_id');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父级区域ID，逻辑外键 regions.id');
            $table->string('code', 64)->comment('区域编码，全局唯一');
            $table->string('name', 128)->comment('区域名称');
            $table->string('path', 255)->nullable()->comment('物化路径，如 1/2/');
            $table->unsignedInteger('level')->default(1)->comment('层级，根节点为 1');
            $table->string('status', 20)->default('active')->comment('状态：active/disabled');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code', 'regions_code_unique');
            $table->index(['tenant_id', 'status'], 'regions_tenant_status_idx');
            $table->index('parent_id', 'regions_parent_id_idx');
            $table->index('path', 'regions_path_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
