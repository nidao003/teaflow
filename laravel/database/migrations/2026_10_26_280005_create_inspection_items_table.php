<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inspection_id')->comment('巡检单 inspections.id');
            $table->string('item', 128)->comment('检查项名称');
            $table->string('result', 16)->nullable()->comment('passed/failed/na，null 表示未评分');
            $table->string('note', 255)->nullable()->comment('备注');
            $table->string('photo', 255)->nullable()->comment('照片地址');
            $table->timestamps();

            $table->index('inspection_id', 'inspection_items_inspection_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
    }
};
