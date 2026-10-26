<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 191);
            $table->string('unit', 32)->comment('计量单位：g/ml/个');
            $table->string('category', 64)->nullable()->index()->comment('原料分类');
            $table->integer('shelf_life_days')->nullable()->comment('保质期天数');
            $table->decimal('safety_stock', 12, 3)->default(0)->comment('安全库存');
            $table->timestamps();

            $table->unique(['name', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
