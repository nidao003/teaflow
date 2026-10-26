<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 只追加台账：没有 updated_at，模型层禁止 update/delete。
        Schema::create('member_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->comment('members.id');
            $table->string('account_type', 16)->comment('balance 储值 / points 积分');
            $table->string('direction', 8)->comment('in 入账 / out 出账');
            $table->decimal('amount', 10, 2)->comment('变动金额（正数，方向由 direction 表达）');
            $table->string('biz_type', 16)->comment('recharge/consume/refund/adjust/expire');
            $table->string('biz_id', 64)->nullable()->comment('业务单据号');
            $table->string('idempotency_key', 128)->comment('幂等键，唯一');
            $table->decimal('balance_after', 10, 2)->comment('本次入账后的账户余额');
            $table->unsignedBigInteger('operator_id')->nullable()->comment('操作人');
            $table->timestamp('created_at')->nullable();

            $table->unique('idempotency_key');
            $table->index(['member_id', 'account_type', 'id'], 'member_ledger_member_account_idx');
            $table->index(['biz_type', 'biz_id'], 'member_ledger_biz_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_ledger');
    }
};
