<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_sku_id')->index()->comment('product_skus.id');
            $table->unsignedInteger('version')->comment('SKU 内递增版本号');
            $table->unsignedBigInteger('recipe_id')->nullable()->index()->comment('本次计算使用的 recipes.id');
            $table->decimal('unit_cost', 10, 2)->default(0)->comment('单位成本');
            $table->dateTime('calc_at')->comment('成本计算对应的业务时点');
            $table->json('snapshot_json')->nullable()->comment('成本快照：配方版本、明细、原料单价来源');
            $table->timestamps();

            $table->unique(['product_sku_id', 'version']);
            $table->index(['product_sku_id', 'calc_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_cards');
    }
};
