<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 给 users 增加组织归属与数据范围字段（只加列，不改动原有列）。
 *
 * - org_unit_id：所属组织（逻辑外键 -> org_units.id），平台级账号可为空。
 * - data_scope：数据范围，取值 all/region/store/self，默认 self（最小权限）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('org_unit_id')->nullable()->after('type')->comment('所属组织ID，逻辑外键');
            $table->string('data_scope', 20)->default('self')->after('org_unit_id')->comment('数据范围：all/region/store/self');

            $table->index('org_unit_id', 'users_org_unit_id_idx');
            $table->index('data_scope', 'users_data_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_org_unit_id_idx');
            $table->dropIndex('users_data_scope_idx');
            $table->dropColumn(['org_unit_id', 'data_scope']);
        });
    }
};
