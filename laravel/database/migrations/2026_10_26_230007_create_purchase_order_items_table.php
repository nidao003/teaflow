<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_order_id')->index()->comment('采购单');
            // material 原料 / product 商品
            $table->string('item_type', 16)->default('material')->comment('物料类型');
            $table->unsignedBigInteger('item_id')->comment('物料/商品 id');
            $table->string('item_name', 128)->nullable()->comment('物料名称快照');
            $table->string('unit', 16)->default('unit')->comment('计量单位');
            $table->decimal('qty', 12, 3)->comment('采购数量');
            $table->decimal('received_qty', 12, 3)->default(0)->comment('已收数量');
            $table->decimal('unit_price', 10, 2)->default(0)->comment('采购单价');
            $table->decimal('amount', 10, 2)->default(0)->comment('行金额');
            // 收货建批次用（可选）
            $table->string('batch_no', 64)->nullable()->comment('批次号');
            $table->date('production_date')->nullable()->comment('生产日期');
            $table->date('expiry_date')->nullable()->comment('到期日期');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
