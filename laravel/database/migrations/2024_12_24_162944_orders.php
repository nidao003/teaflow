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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            //order_number  订单号
            $table->string('order_number');
            //get_code 取餐码
            $table->string('get_code');
            // seller_id 商家id
            $table->integer('seller_id')->default(0)->nullable();
            // store_id 店铺id
            $table->integer('store_id')->default(0)->nullable();
            // user_id 用户id
            $table->integer('user_id');
            // 自提或者外卖 takeout self-pickup
            $table->string('delivery_type')->default('self-pickup')->nullable();
            // order_status 订单状态
            $table->string('order_status')->default('pending')->nullable();
            // pay_status 支付状态
            $table->string('pay_status')->default('pending')->nullable();
            // delivery_status 配送状态
            $table->string('delivery_status')->default('pending')->nullable();
            // payment_method 支付方式
            $table->string('payment_method')->default('cash')->nullable();
            // delivery_method 配送方式
            $table->string('delivery_method')->default('delivery')->nullable();
            // 支付成功时间
            $table->timestamp('paid_at')->nullable();
            // total_quantity 订单总数量
            $table->integer('total_quantity')->default(0)->nullable();
            //小料金额
            $table->decimal('total_mix_amount', 10, 2)->default(0)->nullable();
            // total_amount 订单总金额
            $table->decimal('total_amount', 10, 2)->default(0)->nullable();
            // 实付
            $table->decimal('paid_amount', 10, 2)->default(0)->nullable();
            // 扣除已退金额，剩余金额
            $table->decimal('real_amount', 10, 2)->default(0)->nullable();
            // 已退款
            $table->decimal('refunded_amount', 10, 2)->default(0)->nullable();
            // 等待取茶时间
            $table->timestamp('waiting_at')->nullable();
            // 取餐时间
            $table->timestamp('get_time')->nullable();
            // 完成时间
            $table->timestamp('finished_at')->nullable();
            // 备注
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
