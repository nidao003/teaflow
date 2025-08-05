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
        Schema::create('order_delivery', function (Blueprint $table) {
            //id
            $table->id();
            $table->integer('order_id');
            // delivery_fee 配送费
            $table->decimal('delivery_fee', 10, 2)->default(0)->nullable();
            // 配送开始时间
            $table->timestamp('dispatched_at')->nullable();
            // 配送成功时间
            $table->timestamp('delivered_at')->nullable();
            // 配送员信息
            $table->string('deliveryman_name')->nullable();
            $table->string('deliveryman_phone')->nullable();
            // 配送平台
            $table->string('delivery_platform')->nullable();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_delivery');
    }
};
