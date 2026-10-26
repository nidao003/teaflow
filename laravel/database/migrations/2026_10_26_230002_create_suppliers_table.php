<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->nullable()->unique()->comment('供应商编码');
            $table->string('name', 128)->comment('供应商名称');
            $table->string('contact', 64)->nullable()->comment('联系人');
            $table->string('phone', 32)->nullable()->comment('联系电话');
            $table->string('address', 255)->nullable()->comment('地址');
            // active / disabled
            $table->string('status', 16)->default('active')->index()->comment('状态');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
