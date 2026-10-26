<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 营业收入日汇总：由 orders / payments / refunds 聚合而来（派生表，可重算）
        Schema::create('revenue_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('门店');
            $table->date('date')->comment('营业日 Y-m-d');
            $table->unsignedInteger('order_count')->default(0)->comment('订单数（按下单日、排除已取消）');
            $table->decimal('gross_amount', 10, 2)->default(0)->comment('支付成功金额');
            $table->decimal('discount_amount', 10, 2)->default(0)->comment('折扣金额 total_amount-paid_amount');
            $table->decimal('refund_amount', 10, 2)->default(0)->comment('退款金额（按退款成功日归属）');
            $table->decimal('net_amount', 10, 2)->default(0)->comment('净收入 = 支付成功 - 退款');
            $table->decimal('payment_fee', 10, 2)->default(0)->comment('支付手续费');
            $table->timestamps();

            $table->unique(['store_id', 'date'], 'revenue_daily_store_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_daily');
    }
};
