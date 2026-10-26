<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_menu_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('门店 id');
            $table->unsignedBigInteger('product_sku_id')->index()->comment('product_skus.id');
            $table->decimal('price', 10, 2)->default(0)->comment('门店价，覆盖平台价');
            $table->string('status', 8)->default('on')->comment('on 上架 / off 下架');
            $table->boolean('sold_out')->default(false)->comment('是否售罄');
            $table->dateTime('sold_out_at')->nullable()->comment('售罄时间');
            $table->time('available_from')->nullable()->comment('可售开始时间，空表示不限');
            $table->time('available_to')->nullable()->comment('可售结束时间，空表示不限');
            $table->timestamps();

            $table->unique(['store_id', 'product_sku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_menu_items');
    }
};
