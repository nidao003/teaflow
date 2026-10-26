<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 支付单：一个订单可多次发起支付，只有成功的支付单会回写 orders.pay_status。
        // 金额来源必须是服务端订单，禁止信任客户端传入的金额。
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->comment('orders.order_number（逻辑外键）');
            $table->unsignedBigInteger('store_id')->nullable()->comment('门店 id');
            $table->string('channel', 16)->comment('wechat / alipay / cash');
            $table->string('out_trade_no', 64)->unique()->comment('商户支付单号');
            $table->string('transaction_id', 64)->nullable()->unique()->comment('渠道交易号，回调后回填');
            $table->decimal('amount', 10, 2)->comment('支付金额（取服务端订单 real_amount/total_amount）');
            $table->string('status', 16)->default('created')->comment('created/paying/success/failed/closed');
            $table->timestamp('paid_at')->nullable()->comment('支付成功时间');
            $table->json('raw_notify')->nullable()->comment('渠道回调原文');
            $table->string('idempotency_key', 128)->unique()->comment('创建支付单的幂等键');
            $table->timestamps();

            $table->index(['order_number', 'status'], 'payments_order_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
