<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 订单状态审计：只追加，不更新、不删除；非法迁移也会留痕。
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->comment('orders.order_number（逻辑外键）');
            $table->string('from_status', 32)->nullable()->comment('迁移前状态');
            $table->string('to_status', 32)->comment('迁移后状态');
            $table->string('event', 64)->comment('事件名，如 payment_succeeded / refund_succeeded');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人，系统事件为 null');
            $table->json('payload')->nullable()->comment('事件附加信息');
            $table->timestamp('created_at')->nullable()->comment('创建时间');

            $table->index(['order_number', 'id'], 'order_events_order_index');
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
