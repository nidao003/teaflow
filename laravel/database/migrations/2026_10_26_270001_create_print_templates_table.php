<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->nullable()->index()->comment('门店 id，空表示通用模板');
            $table->string('type', 16)->default('receipt')->comment('receipt/label/production');
            $table->string('name', 191);
            $table->text('content')->comment('模板内容，变量占位符 {{var}}');
            $table->unsignedSmallInteger('width')->default(58)->comment('打印纸宽（mm）');
            $table->tinyInteger('status')->default(1)->comment('1 启用 -1 停用');
            $table->timestamps();

            $table->index(['store_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_templates');
    }
};
