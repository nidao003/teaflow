<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 渠道异步回调审计表：notify_id 幂等；未验签/金额不符的回调也会留痕（verified=false）。
        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 16)->comment('wechat / alipay / cash');
            $table->string('notify_id', 64)->unique()->comment('渠道通知 id（幂等键）');
            $table->json('payload')->nullable()->comment('回调原文');
            $table->boolean('verified')->default(false)->comment('验签结果');
            $table->timestamp('processed_at')->nullable()->comment('处理完成时间，null=未处理');
            $table->timestamps();

            $table->index(['channel', 'processed_at'], 'payment_callbacks_channel_processed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
    }
};
