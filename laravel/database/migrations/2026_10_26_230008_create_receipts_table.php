<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 64)->unique()->comment('收货单号');
            $table->unsignedBigInteger('purchase_order_id')->index()->comment('采购单');
            $table->unsignedBigInteger('warehouse_id')->index()->comment('收货仓库');
            $table->unsignedBigInteger('supplier_id')->nullable()->index()->comment('供应商');
            $table->unsignedBigInteger('store_id')->nullable()->index()->comment('门店');
            $table->decimal('received_qty', 12, 3)->default(0)->comment('收货数量');
            $table->decimal('total_amount', 10, 2)->default(0)->comment('收货金额');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人');
            $table->timestamp('received_at')->nullable()->comment('收货时间');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
