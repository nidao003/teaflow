<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->comment('门店 stores.id，逻辑外键');
            $table->string('template_name', 64)->comment('巡检模板名称');
            $table->unsignedBigInteger('inspector_id')->nullable()->comment('巡检人 staff.id');
            $table->decimal('score', 5, 2)->default(0)->comment('巡检得分（0-100）');
            $table->string('status', 16)->default('draft')->comment('draft/passed/failed/rectifying/closed');
            $table->timestamp('checked_at')->nullable()->comment('提交检查时间');
            $table->timestamps();

            $table->index(['store_id', 'checked_at'], 'inspections_store_checked_idx');
            $table->index('status', 'inspections_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
