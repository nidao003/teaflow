<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('package_id')->index()->comment('packages.id');
            $table->unsignedBigInteger('product_sku_id')->index()->comment('product_skus.id');
            $table->unsignedInteger('qty')->default(1)->comment('包含数量');
            $table->timestamps();

            $table->unique(['package_id', 'product_sku_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_items');
    }
};
