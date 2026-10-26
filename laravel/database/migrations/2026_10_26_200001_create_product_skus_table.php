<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_skus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index()->comment('所属商品 products.id');
            $table->string('spec_key', 191)->comment('规格键，如 大杯/半糖');
            $table->decimal('price', 10, 2)->default(0)->comment('售价');
            $table->decimal('market_price', 10, 2)->default(0)->comment('市场价');
            $table->decimal('cost', 10, 2)->default(0)->comment('最近成本');
            $table->tinyInteger('status')->default(1)->comment('1 启用 -1 停用');
            $table->timestamps();

            $table->unique(['product_id', 'spec_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_skus');
    }
};
