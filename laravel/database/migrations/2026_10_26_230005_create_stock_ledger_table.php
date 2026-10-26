<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 库存流水：只追加，不更新、不删除，是库存变动的唯一出口
        Schema::create('stock_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->index()->comment('仓库');
            $table->string('item_type', 16)->default('material')->comment('物料类型');
            $table->unsignedBigInteger('item_id')->comment('物料/商品 id');
            // in 入库 / out 出库
            $table->string('direction', 8)->comment('出入方向');
            // purchase/receipt/consume/transfer/adjust/waste/return
            $table->string('biz_type', 16)->index()->comment('业务类型');
            // 业务单号/单据 id（采购单号、盘点单号等）
            $table->string('biz_id', 64)->nullable()->comment('业务单据');
            $table->decimal('qty', 12, 3)->comment('变动数量（正数）');
            $table->decimal('unit_cost', 10, 2)->default(0)->comment('单位成本');
            $table->decimal('amount', 10, 2)->default(0)->comment('变动金额');
            $table->unsignedBigInteger('batch_id')->nullable()->index()->comment('批次');
            // 本次变动后的库存余额（按仓库+物料维度）
            $table->decimal('balance_after', 12, 3)->default(0)->comment('变动后余额');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人');
            $table->timestamp('created_at')->nullable()->comment('创建时间');

            $table->index(['warehouse_id', 'item_type', 'item_id'], 'stock_ledger_stock_index');
            $table->index(['biz_type', 'biz_id'], 'stock_ledger_biz_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledger');
    }
};
