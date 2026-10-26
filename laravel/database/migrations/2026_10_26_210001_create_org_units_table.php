<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 组织单元表：公司(company) -> 区域(region) -> 门店(store)。
 *
 * 说明：
 * - parent_id / tenant_id 均为「逻辑外键」：只建索引，不建数据库外键约束（便于分库/迁移）。
 * - path 为物化路径（如 "1/2/5/"），用于按前缀查子树，避免递归查询。
 * - status 使用字符串常量（active / disabled），不使用数据库 enum。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->comment('租户ID，逻辑外键，暂不建库级外键');
            $table->unsignedBigInteger('parent_id')->nullable()->comment('父级组织ID，逻辑外键');
            $table->string('type', 20)->comment('组织类型：company/region/store');
            $table->string('code', 64)->comment('组织编码，全局唯一');
            $table->string('name', 128)->comment('组织名称');
            $table->string('path', 255)->nullable()->comment('物化路径，如 1/2/5/，用于前缀查子树');
            $table->unsignedInteger('level')->default(1)->comment('层级，根节点为 1');
            $table->unsignedInteger('sort')->default(0)->comment('同级排序，小的在前');
            $table->string('status', 20)->default('active')->comment('状态：active/disabled');
            $table->timestamps();
            $table->softDeletes();

            $table->unique('code', 'org_units_code_unique');
            $table->index(['tenant_id', 'type', 'status'], 'org_units_tenant_type_status_idx');
            $table->index(['parent_id', 'sort'], 'org_units_parent_sort_idx');
            $table->index('path', 'org_units_path_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_units');
    }
};
