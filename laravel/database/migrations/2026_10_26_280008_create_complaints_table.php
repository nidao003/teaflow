<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->comment('门店 stores.id，逻辑外键');
            $table->string('order_number', 64)->nullable()->comment('关联订单号，可空');
            $table->string('type', 32)->comment('客诉类型：service/product/environment 等');
            $table->text('content')->comment('客诉内容');
            $table->string('status', 16)->default('pending')->comment('pending/processing/resolved/closed');
            $table->unsignedBigInteger('handler_id')->nullable()->comment('处理人 staff.id');
            $table->text('resolution')->nullable()->comment('处理结果');
            $table->timestamp('handled_at')->nullable()->comment('处理完成时间');
            $table->timestamps();

            $table->index(['store_id', 'status'], 'complaints_store_status_idx');
            $table->index('handler_id', 'complaints_handler_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
