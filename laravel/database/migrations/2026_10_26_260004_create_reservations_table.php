<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable()->index()->comment('会员 id，可空（散客）');
            $table->unsignedBigInteger('store_id')->index()->comment('门店');
            $table->unsignedBigInteger('slot_id')->index()->comment('预约时段 reservation_slots.id');
            $table->unsignedInteger('party_size')->default(1)->comment('预约人数');
            // booked 已预约 / arrived 已到店 / converted 已转单 / cancelled 已取消 / no_show 爽约
            $table->string('status', 16)->default('booked')->index()->comment('状态');
            $table->string('order_number', 64)->nullable()->index()->comment('转单后订单号（只写一次）');
            $table->string('note', 255)->nullable()->comment('备注');
            $table->timestamp('arrived_at')->nullable()->comment('到店时间');
            $table->timestamp('converted_at')->nullable()->comment('转单时间');
            $table->timestamp('cancelled_at')->nullable()->comment('取消时间');
            $table->timestamp('no_show_at')->nullable()->comment('爽约时间');
            $table->timestamps();

            $table->index(['store_id', 'status'], 'reservations_store_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
