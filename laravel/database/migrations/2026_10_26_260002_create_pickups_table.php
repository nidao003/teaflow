<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickups', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 64)->unique()->comment('订单号（一单一自提，唯一）');
            $table->string('pickup_no', 32)->index()->comment('取餐号');
            $table->unsignedBigInteger('store_id')->index()->comment('门店');
            // pending 待制作 / ready 可取餐 / picked 已取 / cancelled 已取消
            $table->string('status', 16)->default('pending')->index()->comment('状态');
            $table->timestamp('ready_at')->nullable()->comment('可取餐时间');
            $table->timestamp('picked_at')->nullable()->comment('取餐完成时间');
            $table->timestamp('cancelled_at')->nullable()->comment('取消时间');
            $table->timestamps();

            $table->index(['store_id', 'status'], 'pickups_store_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pickups');
    }
};
