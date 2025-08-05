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
         * store_id
         * seller_id
         * product_id
         * status  1 online -1 offline
         */
        Schema::create('product_store', function (Blueprint $table) {
            $table->id();
            $table->integer('store_id')->nullable()->default(0);
            $table->integer('seller_id')->nullable()->default(0);
            $table->integer('product_id');
            $table->integer('status')->nullable()->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_store');
    }
};
