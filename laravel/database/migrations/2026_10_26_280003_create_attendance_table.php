<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id')->comment('员工 staff.id');
            $table->date('date')->comment('考勤日期');
            $table->string('clock_in', 5)->nullable()->comment('签到时间 HH:MM');
            $table->string('clock_out', 5)->nullable()->comment('签退时间 HH:MM');
            $table->string('status', 16)->default('present')->comment('present/late/early_leave/absent/leave');
            $table->string('source', 16)->default('manual')->comment('manual/device/import/system');
            $table->timestamps();

            $table->unique(['staff_id', 'date'], 'attendance_staff_date_unique');
            $table->index(['date', 'status'], 'attendance_date_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
