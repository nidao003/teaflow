<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_sku_id')->index()->comment('product_skus.id');
            $table->unsignedInteger('version')->comment('SKU 内递增版本号');
            $table->date('effective_from')->comment('生效起始日（含）');
            $table->date('effective_to')->nullable()->comment('生效截止日（含），空表示长期有效');
            $table->string('status', 16)->default('draft')->comment('draft/active/archived');
            $table->timestamps();

            $table->unique(['product_sku_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
