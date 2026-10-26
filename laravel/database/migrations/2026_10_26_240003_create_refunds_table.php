<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 退款单：refund_no 唯一；累计退款（已退款 + 处理中）不得超过订单实付金额。
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->comment('orders.order_number（逻辑外键）');
            $table->unsignedBigInteger('payment_id')->nullable()->comment('payments.id（原支付单）');
            $table->string('refund_no', 64)->unique()->comment('商户退款单号');
            $table->decimal('amount', 10, 2)->comment('退款金额');
            $table->string('reason')->nullable()->comment('退款原因');
            $table->string('status', 16)->default('created')->comment('created/processing/success/failed');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('发起人');
            $table->unsignedBigInteger('approved_by')->nullable()->comment('审批人');
            $table->timestamp('refunded_at')->nullable()->comment('退款成功时间');
            $table->string('channel_refund_id', 64)->nullable()->comment('渠道退款单号');
            $table->timestamps();

            $table->index(['order_number', 'status'], 'refunds_order_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
