<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('门店 id');
            $table->string('order_number', 64)->nullable()->index()->comment('关联订单号');
            $table->unsignedBigInteger('template_id')->nullable()->index()->comment('print_templates.id');
            $table->string('device_id', 64)->nullable()->comment('打印机标识：文件路径/网络地址/硬件编号');
            $table->string('type', 16)->default('receipt')->comment('receipt/label/production');
            $table->json('payload')->nullable()->comment('渲染后的打印内容与变量');
            $table->string('status', 16)->default('pending')->index()->comment('pending/printing/success/failed');
            $table->unsignedSmallInteger('retries')->default(0)->comment('失败重试次数');
            $table->text('error')->nullable()->comment('最近一次失败原因');
            $table->dateTime('printed_at')->nullable()->comment('打印成功时间');
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};
