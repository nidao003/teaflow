<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->comment('门店 stores.id，逻辑外键');
            $table->unsignedBigInteger('assignee_id')->nullable()->comment('负责人 staff.id，可空');
            $table->string('title', 128)->comment('任务标题');
            $table->text('description')->nullable()->comment('任务说明');
            $table->timestamp('due_at')->nullable()->comment('截止时间');
            $table->string('status', 16)->default('pending')->comment('pending/in_progress/done/cancelled');
            $table->string('priority', 8)->default('normal')->comment('low/normal/high/urgent');
            $table->timestamp('completed_at')->nullable()->comment('完成时间');
            $table->timestamps();

            $table->index(['store_id', 'status'], 'tasks_store_status_idx');
            $table->index(['assignee_id', 'status'], 'tasks_assignee_status_idx');
            $table->index('due_at', 'tasks_due_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
