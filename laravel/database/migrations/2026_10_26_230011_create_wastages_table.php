<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wastages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->index()->comment('仓库');
            $table->string('item_type', 16)->default('material')->comment('物料类型');
            $table->unsignedBigInteger('item_id')->comment('物料/商品 id');
            $table->unsignedBigInteger('batch_id')->nullable()->index()->comment('批次');
            $table->decimal('qty', 12, 3)->comment('报损数量');
            $table->decimal('unit_cost', 10, 2)->default(0)->comment('单位成本');
            $table->decimal('amount', 10, 2)->default(0)->comment('报损金额');
            $table->string('reason', 255)->nullable()->comment('报损原因');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人');
            $table->timestamp('wasted_at')->nullable()->comment('报损时间');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wastages');
    }
};
