<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->comment('members.id');
            $table->string('account_type', 16)->comment('balance 储值 / points 积分');
            $table->decimal('balance', 10, 2)->default(0)->comment('账户余额（不得为负，由服务层保证）');
            $table->decimal('frozen', 10, 2)->default(0)->comment('冻结金额');
            $table->unsignedBigInteger('version')->default(0)->comment('乐观锁版本号，每次变动 +1');
            $table->timestamps();

            $table->unique(['member_id', 'account_type']);
            $table->index('member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_accounts');
    }
};
