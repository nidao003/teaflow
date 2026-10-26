<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->index()->comment('仓库');
            // material 原料 / product 商品
            $table->string('item_type', 16)->default('material')->comment('物料类型');
            $table->unsignedBigInteger('item_id')->comment('物料/商品 id');
            $table->string('unit', 16)->default('unit')->comment('计量单位');
            // 库存数量（当前余额）
            $table->decimal('qty', 12, 3)->default(0)->comment('库存数量');
            // 锁定量：已预留未出库，可用量 = qty - locked_qty
            $table->decimal('locked_qty', 12, 3)->default(0)->comment('锁定数量');
            // 加权平均单位成本（金额统一 decimal(10,2)）
            $table->decimal('avg_cost', 10, 2)->default(0)->comment('加权平均成本');
            // 乐观锁版本号，每次变动 +1
            $table->unsignedInteger('version')->default(0)->comment('乐观锁版本号');
            $table->timestamps();

            $table->unique(['warehouse_id', 'item_type', 'item_id'], 'stock_items_warehouse_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_items');
    }
};
