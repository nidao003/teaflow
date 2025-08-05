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
        Schema::create('coupon', function (Blueprint $table) {
            $table->id();
            $table->string('title'); //优惠券名称
            $table->string('code')->nullable()->default(null); //优惠券码
            $table->string('type');  // money 代金券  percent 折扣券   direct 直减券
            //来源
            $table->string('source')->nullable()->default('local');
            $table->string('source_id')->nullable()->default(null);//来源ID
            $table->decimal('discount', 10, 2)->nullable()->default(null);
            $table->integer('limit')->nullable()->default(0); //限领次数
            //到期时间
            $table->dateTime('expire_at')->nullable()->default(null);
            $table->integer('status')->nullable()->default(0); // 0未使用 1已使用 , 2过期，3过期已退款
            // data json
            $table->json('data')->nullable()->default(null);
            // days
            $table->integer('days')->nullable()->default(0);
            // send_way 发放方式
            $table->string('send_way')->nullable()->default(null);
            // num 发放数量
            $table->integer('num')->nullable()->default(0);
            //desc
            $table->text('desc')->nullable();
            $table->timestamps();
        });
        // coupon_user
        Schema::create('coupon_user', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('coupon_id');
            //status 默认 wait
            $table->string('status')->nullable()->default('wait');
            // expire_at
            $table->dateTime('expire_at')->nullable()->default(null);
            //order_number
            $table->string('order_number')->nullable()->default(null);
            // get_way
            $table->string('get_way')->nullable()->default(null);
            $table->timestamps();
        });

        // coupon_order
        Schema::create('coupon_order', function (Blueprint $table) {
            $table->id();
            $table->integer('order_id');
            $table->integer('coupon_id');
            //user_id
            $table->integer('user_id');
            //status 默认 wait
            $table->string('status')->nullable()->default('wait');
            //price
            $table->decimal('price', 10, 2)->nullable()->default(0);
            $table->timestamps();
        });

        // coupon_product
        Schema::create('coupon_product', function (Blueprint $table) {
            $table->id();
            $table->integer('product_id');
            $table->integer('coupon_id');
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
