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
         * 退款订单
         */
        Schema::create('order_refund', function (Blueprint $table) {
            $table->id();
            //order_id
            $table->integer('order_id');
            $table->string('order_number');
            //退款单号
            $table->string('refund_number');
            //支付平台返回的订单号
            $table->string('refund_number_platform')->nullable();
            $table->decimal('refund_amount', 10, 2);
            $table->string('refund_status')->default('pending');
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_refund');
    }
};
