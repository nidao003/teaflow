<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_price_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_sku_id')->comment('product_skus.id，逻辑外键');
            $table->unsignedBigInteger('member_level_id')->comment('member_levels.id，逻辑外键');
            $table->decimal('price', 10, 2)->comment('会员专享价');
            $table->date('effective_from')->nullable()->comment('生效开始日期，空表示立即生效');
            $table->date('effective_to')->nullable()->comment('生效结束日期，空表示长期有效');
            $table->unsignedTinyInteger('status')->default(1)->comment('1 启用 0 停用');
            $table->timestamps();

            $table->unique(
                ['product_sku_id', 'member_level_id', 'effective_from'],
                'member_price_rules_sku_level_from_unique'
            );
            $table->index(['member_level_id', 'status'], 'member_price_rules_level_status_idx');
            $table->index('product_sku_id', 'member_price_rules_sku_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_price_rules');
    }
};
