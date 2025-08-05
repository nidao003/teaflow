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
         * 文章表 articles
         * title,body,type_id,sort,created_at,updated_at
         */
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('标题');
            $table->text('body')->comment('内容');
            $table->unsignedBigInteger('type_id')->comment('分类');
            $table->unsignedTinyInteger('sort')->default(0)->comment('排序');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
