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
        Schema::create('order_pay', function (Blueprint $table) {
            $table->id();
            //order_id 订单号
            $table->integer('order_id');
            // title 标题如 优惠券、代金券、美团兑换券
            $table->string('type');
            $table->string('title')->nullable();
            $table->string('desc')->nullable();
            // amount 金额
            $table->decimal('amount', 10, 2);
            // created_at 创建时间
            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_pays');
    }
};
