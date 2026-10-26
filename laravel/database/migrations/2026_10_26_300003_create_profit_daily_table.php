<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 毛利日汇总：revenue - cost，毛利率按百分比（两位小数）保存
        Schema::create('profit_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('门店');
            $table->date('date')->comment('营业日 Y-m-d');
            $table->decimal('revenue', 10, 2)->default(0)->comment('收入（取 revenue_daily.net_amount）');
            $table->decimal('cost', 10, 2)->default(0)->comment('成本（原料+人工+其他）');
            $table->decimal('gross_profit', 10, 2)->default(0)->comment('毛利 = revenue - cost');
            $table->decimal('gross_margin', 8, 2)->default(0)->comment('毛利率（百分比，如 40.00 表示 40%）');
            $table->timestamps();

            $table->unique(['store_id', 'date'], 'profit_daily_store_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profit_daily');
    }
};
