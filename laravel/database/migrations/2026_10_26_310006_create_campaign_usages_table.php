<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->comment('campaigns.id');
            $table->unsignedBigInteger('member_id')->comment('members.id');
            $table->string('order_number', 64)->nullable()->comment('订单号，用于幂等');
            $table->decimal('amount', 10, 2)->default(0)->comment('使用时的订单金额');
            $table->timestamp('used_at')->nullable()->comment('使用时间');
            $table->timestamps();

            $table->unique(
                ['campaign_id', 'member_id', 'order_number'],
                'campaign_usages_campaign_member_order_unique'
            );
            $table->index('campaign_id', 'campaign_usages_campaign_idx');
            $table->index('member_id', 'campaign_usages_member_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_usages');
    }
};
