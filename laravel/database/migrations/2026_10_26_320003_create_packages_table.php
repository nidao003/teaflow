<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->decimal('price', 10, 2)->default(0)->comment('套餐价');
            $table->string('status', 8)->default('on')->comment('on 在售 / off 停售');
            $table->dateTime('start_at')->nullable()->comment('开售时间，空表示不限');
            $table->dateTime('end_at')->nullable()->comment('停售时间，空表示不限');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
