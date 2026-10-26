<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verify_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique()->comment('核销码（全局唯一，一次性）');
            $table->string('order_number', 64)->index()->comment('关联订单号');
            $table->unsignedBigInteger('store_id')->index()->comment('核销门店');
            // pickup 取餐码 / coupon 券码
            $table->string('type', 16)->default('pickup')->index()->comment('核销码类型');
            // issued 已发 / used 已核销 / expired 已过期 / void 已作废
            $table->string('status', 16)->default('issued')->index()->comment('状态');
            $table->timestamp('used_at')->nullable()->comment('核销时间');
            $table->unsignedBigInteger('used_by')->nullable()->comment('核销人（员工 id）');
            $table->timestamp('expires_at')->nullable()->index()->comment('过期时间');
            $table->timestamps();

            $table->index(['store_id', 'order_number'], 'verify_codes_store_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verify_codes');
    }
};
