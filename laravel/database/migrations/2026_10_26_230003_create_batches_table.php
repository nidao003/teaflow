<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->index()->comment('仓库');
            // material 原料 / product 商品
            $table->string('item_type', 16)->default('material')->index()->comment('物料类型');
            $table->unsignedBigInteger('item_id')->comment('物料/商品 id');
            $table->string('batch_no', 64)->comment('批次号');
            $table->date('production_date')->nullable()->comment('生产日期');
            $table->date('expiry_date')->nullable()->index()->comment('到期日期');
            // 剩余可用数量（FIFO 出库从这里扣减）
            $table->decimal('qty', 12, 3)->default(0)->comment('剩余数量');
            $table->decimal('cost', 10, 2)->default(0)->comment('批次单位成本');
            // active / exhausted / expired
            $table->string('status', 16)->default('active')->index()->comment('批次状态');
            $table->unsignedBigInteger('supplier_id')->nullable()->index()->comment('供应商');
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index()->comment('采购单');
            $table->timestamps();

            $table->unique(['warehouse_id', 'item_type', 'item_id', 'batch_no'], 'batches_stock_batch_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
