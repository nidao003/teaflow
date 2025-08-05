<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * 收款银行信息
         * title 银行名称
         * account_name 银行账号
         * account_phone 银行预留手机号
         * account_address 银行开户地址
         */
        Schema::create('seller_bank', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->string('title');
            $table->string('account_name');
            $table->string('account_phone');
            $table->string('account_address');
            $table->softDeletes();
            $table->timestamps();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_bank');
    }
};
