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
         * title
         * image
         * area
         * address
         * phone
         * type 店铺类型
         */
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('title');
            $table->string('image')->nullable();
            $table->string('area');
            $table->string('address');
            $table->string('phone');
            $table->string('type')->nullable()->default('default');
            //lat lng是经纬度
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            // is_open
            $table->string('is_open')->default(1);
            $table->string('is_delivery')->default(1);
            // open_at
            $table->string('open_at')->nullable();
            // close_at
            $table->string('close_at')->nullable();
            // area_range
            $table->string('area_range')->nullable();
            //tea_cost_time
            $table->string('tea_cost_time')->nullable();
            // user_id
            $table->unsignedBigInteger('user_id')->nullable()->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store');
    }
};
