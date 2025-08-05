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
         * 字段时 id
         * title 为标题
         * content 为内容
         * specs 为规格
         * image 为图片
         * status 为状态 1为正常 -1为禁用
         */
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->json('specs')->nullable();
            $table->string('image');
            $table->tinyInteger('status')->default(1);
            //type_id
            $table->unsignedBigInteger('type_id')->nullable()->default(0);
            //用户id
            $table->unsignedBigInteger('user_id')->nullable()->default(0);
            //sort
            $table->integer('sort')->default(0);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
