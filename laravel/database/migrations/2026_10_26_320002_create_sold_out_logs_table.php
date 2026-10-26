<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sold_out_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->index()->comment('门店 id');
            $table->unsignedBigInteger('product_sku_id')->index()->comment('product_skus.id');
            $table->string('action', 16)->comment('sold_out 售罄 / restored 恢复');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人 id');
            $table->string('note', 255)->nullable()->comment('备注');
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['store_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sold_out_logs');
    }
};
