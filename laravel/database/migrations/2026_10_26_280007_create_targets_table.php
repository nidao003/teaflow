<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->comment('门店 stores.id，逻辑外键');
            $table->string('period', 32)->comment('目标周期，如 2026-10 / 2026-W43');
            $table->string('metric', 32)->comment('目标指标，如 revenue / orders');
            $table->decimal('target_value', 10, 2)->comment('目标值');
            $table->decimal('actual_value', 10, 2)->default(0)->comment('实际值');
            $table->timestamps();

            $table->unique(['store_id', 'period', 'metric'], 'targets_store_period_metric_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('targets');
    }
};
