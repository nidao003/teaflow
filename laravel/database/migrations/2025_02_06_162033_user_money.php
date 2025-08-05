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
         * 用户充值及消费
         */
        Schema::create('user_money', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->decimal('money', 10, 2);
            $table->string('type');
            $table->string('remark');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_money');
    }
};
