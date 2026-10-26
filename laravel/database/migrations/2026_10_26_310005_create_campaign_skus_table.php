<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_skus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->comment('campaigns.id');
            $table->unsignedBigInteger('product_sku_id')->nullable()->comment('product_skus.id；空/无记录表示全场');
            $table->timestamps();

            $table->unique(['campaign_id', 'product_sku_id'], 'campaign_skus_campaign_sku_unique');
            $table->index('product_sku_id', 'campaign_skus_sku_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_skus');
    }
};
