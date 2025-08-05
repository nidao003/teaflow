<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * 订单号、支付平台订单号、支付方式
         */
        Schema::create('pay', function (Blueprint $table) {
            $table->id();
            $table->string('device_id')->nullable();
            $table->string('store_id')->nullable()->default(0);
            $table->string('order_number');
            $table->string('pay_number');
            $table->string('pay_method');
            //支付金额
            $table->decimal('total_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2);
            //支付状态
            $table->string('pay_status')->default('pending');
            //支付时间
            $table->dateTime('paid_at')->nullable();
            // refund 订单号
            $table->string('refund_number')->nullable();
            //支付平台返回的订单号
            $table->string('refund_number_platform')->nullable();
            $table->decimal('refunded_amount', 10, 2)->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pay');
    }
};
