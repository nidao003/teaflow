<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 渠道对账日汇总：系统金额 vs 渠道实收，差异超阈值标 exception
        Schema::create('reconciliation_daily', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('门店');
            $table->date('date')->comment('对账日 Y-m-d');
            $table->string('channel', 16)->comment('支付渠道 wechat/alipay/cash');
            $table->decimal('system_amount', 10, 2)->default(0)->comment('系统金额');
            $table->decimal('channel_amount', 10, 2)->default(0)->comment('渠道金额');
            $table->decimal('diff', 10, 2)->default(0)->comment('差异 = 渠道 - 系统');
            // pending 待对账 / matched 已匹配 / exception 异常
            $table->string('status', 16)->default('pending')->index()->comment('对账状态');
            $table->timestamps();

            $table->unique(['store_id', 'date', 'channel'], 'reconciliation_daily_store_date_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_daily');
    }
};
