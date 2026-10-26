<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->id();
            $table->string('stocktake_no', 64)->unique()->comment('盘点单号');
            $table->unsignedBigInteger('warehouse_id')->index()->comment('仓库');
            // counting 盘点中 / counted 已盘待调账 / completed 已完成 / cancelled 已取消
            $table->string('status', 16)->default('counting')->index()->comment('状态');
            $table->unsignedInteger('total_items')->default(0)->comment('盘点行数');
            $table->unsignedInteger('diff_items')->default(0)->comment('差异行数');
            $table->decimal('diff_amount', 10, 2)->default(0)->comment('差异金额');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('创建人');
            $table->unsignedBigInteger('adjusted_by')->nullable()->comment('调账人');
            $table->timestamp('counted_at')->nullable()->comment('盘点完成时间');
            $table->timestamp('completed_at')->nullable()->comment('调账完成时间');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktakes');
    }
};
