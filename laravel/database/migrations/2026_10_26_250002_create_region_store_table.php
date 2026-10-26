<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 区域-门店关联表。
 *
 * unique(store_id)：一家门店只能属于一个区域；区域侧不唯一（一个区域可挂多家门店）。
 * 与 stores.region_id 的分工：
 * - region_store 为关联事实表（唯一约束兜底）；
 * - stores.region_id 为反范式字段，供列表/汇总查询直接过滤。
 * 两者由 RegionService::assignStore 在同一事务内同步。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('region_store', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('region_id')->comment('区域 regions.id，逻辑外键');
            $table->unsignedBigInteger('store_id')->comment('门店 stores.id，逻辑外键');
            $table->timestamps();

            $table->unique('store_id', 'region_store_store_id_unique');
            $table->index('region_id', 'region_store_region_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_store');
    }
};
