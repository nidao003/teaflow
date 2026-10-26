<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->comment('门店 stores.id，逻辑外键');
            $table->unsignedBigInteger('staff_id')->comment('员工 staff.id');
            $table->date('date')->comment('排班日期');
            $table->string('shift_type', 32)->comment('班次类型：morning/afternoon/evening/full');
            $table->string('start_time', 5)->comment('开始时间 HH:MM');
            $table->string('end_time', 5)->comment('结束时间 HH:MM，小于开始时间视为跨天班次');
            $table->string('status', 16)->default('scheduled')->comment('scheduled/cancelled/finished');
            $table->timestamps();

            $table->unique(['staff_id', 'date', 'shift_type'], 'schedules_staff_date_shift_unique');
            $table->index(['store_id', 'date'], 'schedules_store_date_idx');
            $table->index('staff_id', 'schedules_staff_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
