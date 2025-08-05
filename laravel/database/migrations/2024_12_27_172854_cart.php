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
        Schema::create('cart', function (Blueprint $table) {
            $table->id();
            $table->string('device_id');
            $table->integer('store_id');
            $table->string('user_id');
            $table->integer('total_quantity');
            $table->decimal('total_mix_amount', 10, 2)->default(0)->nullable();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total_amount', 10, 2);
            //tag 用于区分购物车类型
            $table->string('tag')->default('user')->nullable();
            $table->timestamps();
        });
        Schema::create('cart_item', function (Blueprint $table) {
            $table->id();
            $table->integer('cart_id');
            $table->integer('product_id');
            // quantity 数量
            $table->integer('num');
            // price 价格
            $table->decimal('price', 10, 2);
            $table->decimal('total_price', 10, 2);
            // subtotal 小计
            $table->decimal('subtotal', 10, 2);
            $table->string('spec');
            $table->string('attr')->nullable();
            $table->string('mix')->nullable();
            // 小料金额 mix_price
            $table->decimal('mix_price', 10, 2)->default(0)->nullable();
            $table->decimal('mix_amount', 10, 2)->default(0)->nullable();
            //是否选中
            $table->integer('checked')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart');
        Schema::dropIfExists('cart_item');
    }
};
