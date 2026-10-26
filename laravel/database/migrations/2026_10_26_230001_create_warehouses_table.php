<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            // 所属门店；为空代表中央仓/虚拟仓
            $table->unsignedBigInteger('store_id')->nullable()->index()->comment('所属门店');
            $table->string('code', 64)->nullable()->unique()->comment('仓库编码');
            $table->string('name', 128)->comment('仓库名称');
            // store 门店仓 / central 中央仓 / virtual 虚拟仓
            $table->string('type', 16)->default('store')->index()->comment('仓库类型');
            // active / disabled
            $table->string('status', 16)->default('active')->index()->comment('状态');
            $table->string('address', 255)->nullable()->comment('地址');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
