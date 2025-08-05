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
         * 商品属性表
         */
        Schema::create('product_attr', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('属性名称');
            $table->integer('sort')->default(0)->comment('排序');
        });
        /**
         * 商品属性值表
         */
        Schema::create('product_attr_value', function (Blueprint $table) {
            $table->id();
            //关联product_attr
            $table->integer('attr_id');
            $table->string('value')->comment('属性值');
            $table->integer('sort')->default(0)->comment('排序');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_attr');
        Schema::dropIfExists('product_attr_value');
    }
};
