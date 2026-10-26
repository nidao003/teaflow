<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique()->comment('标签名称');
            $table->string('color', 16)->default('#409EFF')->comment('标签颜色');
            $table->unsignedTinyInteger('status')->default(1)->comment('1 启用 0 停用');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_tags');
    }
};
