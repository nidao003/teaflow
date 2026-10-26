<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipe_id')->index()->comment('recipes.id');
            $table->unsignedBigInteger('material_id')->index()->comment('materials.id');
            $table->decimal('qty', 12, 3)->comment('标准用量');
            $table->string('unit', 32)->nullable()->comment('用量单位，空则取原料单位');
            $table->decimal('loss_rate', 8, 4)->default(0)->comment('损耗率，0.05 表示 5%');
            $table->timestamps();

            $table->unique(['recipe_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_items');
    }
};
