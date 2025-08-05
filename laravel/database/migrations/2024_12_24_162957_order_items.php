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
        Schema::create('order_items', function (Blueprint $table) {
            //id
            $table->id();
            //order_id 订单号
            $table->integer('order_id');
            // product_id 商品id
            $table->integer('product_id');
            // product_title 商品名称
            $table->string('product_title');
            // product_image 商品图片
            $table->string('product_image');
            // quantity 数量
            $table->integer('quantity');
            // price 价格
            $table->decimal('price', 10, 2);
            // subtotal 小计
            $table->decimal('subtotal', 10, 2);
            $table->string('spec');
            $table->string('attr')->nullable();
            $table->string('mix')->nullable();
            // 小料金额
            $table->decimal('mix_amount', 10, 2)->default(0)->nullable();
            // data json
            $table->json('data')->nullable();
            // created_at 创建时间
            $table->timestamps();
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
