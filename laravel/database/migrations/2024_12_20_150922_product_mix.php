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
         * 商品小料表
         */
        Schema::create('product_mix', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('小料名称');
            //价格默认为0
            $table->decimal('price', 10, 2)->default(0)->comment('小料价格');
            //sort
            $table->integer('sort')->default(0)->comment('排序');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_mix');
    }
};
