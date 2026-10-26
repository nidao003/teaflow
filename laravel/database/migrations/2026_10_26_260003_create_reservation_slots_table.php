<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('门店');
            $table->date('date')->index()->comment('预约日期');
            // 用 HH:MM 文本保存，跨 sqlite/mysql 输出一致
            $table->string('start_time', 8)->comment('开始时间 HH:MM');
            $table->string('end_time', 8)->comment('结束时间 HH:MM');
            $table->unsignedInteger('capacity')->default(0)->comment('容量（可预约人数）');
            $table->unsignedInteger('booked_count')->default(0)->comment('已预约人数');
            // active 开放 / closed 关闭
            $table->string('status', 16)->default('active')->index()->comment('状态');
            $table->timestamps();

            $table->unique(['store_id', 'date', 'start_time'], 'reservation_slots_store_date_start_unique');
            $table->index(['store_id', 'date'], 'reservation_slots_store_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_slots');
    }
};
