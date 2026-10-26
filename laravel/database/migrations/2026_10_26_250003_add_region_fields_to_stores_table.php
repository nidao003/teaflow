<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 给既有 stores 表补充区域/门店域字段（只加列 + 索引，不改原有列）。
 *
 * - region_id：所属区域（逻辑外键 regions.id），反范式字段，便于按区域过滤；
 * - store_code：门店短码，全局唯一（可空，历史数据可后补，NULL 不参与唯一冲突）；
 * - status：营业状态 open/closed/suspended，字符串常量，默认 open；
 * - business_hours：营业时间 JSON，形如 {"mon":[["09:00","22:00"]],"default":[["09:00","22:00"]],"timezone":"Asia/Shanghai"}。
 *
 * 注意：现有 App\Models\Store 不做修改，读取新字段时由域内服务处理（business_hours 为原始 JSON 字符串）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('seller_id')->comment('所属区域 regions.id，逻辑外键');
            $table->string('store_code', 32)->nullable()->after('title')->comment('门店短码，全局唯一');
            $table->string('status', 20)->default('open')->after('region_id')->comment('营业状态：open/closed/suspended');
            $table->json('business_hours')->nullable()->after('status')->comment('营业时间 JSON');

            $table->index('region_id', 'stores_region_id_idx');
            $table->unique('store_code', 'stores_store_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropUnique('stores_store_code_unique');
            $table->dropIndex('stores_region_id_idx');
            $table->dropColumn(['region_id', 'store_code', 'status', 'business_hours']);
        });
    }
};
