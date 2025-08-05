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
        Schema::create('order_printer', function (Blueprint $table) {
            $table->id();
            $table->string('order_number');
            $table->string('printer_no');
            $table->string('status')->nullable()->default('padding');
            $table->string('type')->nullable()->default('58mm');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_printer');
    }
};
