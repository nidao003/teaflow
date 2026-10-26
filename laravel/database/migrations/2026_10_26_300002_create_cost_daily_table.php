<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 营业成本日汇总：原料成本（可由库存流水/成本卡折算）+ 人工 + 其他
        Schema::create('cost_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('门店');
            $table->date('date')->comment('营业日 Y-m-d');
            $table->decimal('material_cost', 10, 2)->default(0)->comment('原料成本');
            $table->decimal('labor_cost', 10, 2)->default(0)->comment('人工成本');
            $table->decimal('other_cost', 10, 2)->default(0)->comment('其他成本');
            $table->timestamps();

            $table->unique(['store_id', 'date'], 'cost_daily_store_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_daily');
    }
};
