<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 64)->unique()->comment('采购单号');
            $table->unsignedBigInteger('store_id')->nullable()->index()->comment('门店');
            $table->unsignedBigInteger('warehouse_id')->nullable()->index()->comment('收货仓库');
            $table->unsignedBigInteger('supplier_id')->index()->comment('供应商');
            // draft / approved / partial_received / received / closed / cancelled
            $table->string('status', 16)->default('draft')->index()->comment('状态');
            $table->decimal('total_qty', 12, 3)->default(0)->comment('采购总数量');
            $table->decimal('total_amount', 10, 2)->default(0)->comment('采购总金额');
            $table->date('expected_at')->nullable()->comment('预计到货日期');
            $table->timestamp('approved_at')->nullable()->comment('审核时间');
            $table->timestamp('received_at')->nullable()->comment('收货完成时间');
            $table->timestamp('closed_at')->nullable()->comment('关闭时间');
            $table->timestamp('cancelled_at')->nullable()->comment('取消时间');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('创建人');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('审核人');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
