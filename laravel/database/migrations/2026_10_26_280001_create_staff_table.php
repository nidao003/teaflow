<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id')->comment('门店 stores.id，逻辑外键');
            $table->unsignedBigInteger('user_id')->nullable()->comment('关联 users.id，可空');
            $table->string('name', 64)->comment('员工姓名');
            $table->string('role', 32)->default('staff')->comment('店内角色：manager/cashier/barista 等');
            $table->string('phone', 32)->nullable()->comment('联系电话');
            $table->unsignedTinyInteger('status')->default(1)->comment('1 在职 0 停用');
            $table->timestamp('hired_at')->nullable()->comment('入职时间');
            $table->timestamps();

            $table->index(['store_id', 'status'], 'staff_store_status_idx');
            $table->index('user_id', 'staff_user_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
