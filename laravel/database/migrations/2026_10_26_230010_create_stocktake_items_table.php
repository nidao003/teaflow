<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktake_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('stocktake_id')->index()->comment('盘点单');
            $table->string('item_type', 16)->default('material')->comment('物料类型');
            $table->unsignedBigInteger('item_id')->comment('物料/商品 id');
            $table->string('unit', 16)->default('unit')->comment('计量单位');
            // 账面数量（创建盘点单时快照）
            $table->decimal('book_qty', 12, 3)->default(0)->comment('账面数量');
            // 实盘数量（未盘为 null）
            $table->decimal('counted_qty', 12, 3)->nullable()->comment('实盘数量');
            // 差异 = 实盘 - 账面
            $table->decimal('diff_qty', 12, 3)->default(0)->comment('差异数量');
            $table->decimal('unit_cost', 10, 2)->default(0)->comment('单位成本');
            $table->decimal('diff_amount', 10, 2)->default(0)->comment('差异金额');
            $table->boolean('adjusted')->default(false)->comment('是否已调账');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_items');
    }
};
