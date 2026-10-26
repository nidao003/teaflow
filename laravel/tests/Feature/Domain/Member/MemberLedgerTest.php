<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Member;

use App\Domain\Member\Exceptions\LedgerImmutableException;
use App\Domain\Member\Models\MemberLedger;
use App\Domain\Member\Services\MemberAccountService;
use Illuminate\Support\Facades\Schema;

class MemberLedgerTest extends MemberTestCase
{
    public function test_ledger_is_append_only(): void
    {
        $member = $this->createMember();
        $ledger = $this->app->make(MemberAccountService::class)
            ->recharge($member->getKey(), '100.00', 'idem-ledger-append-only');

        try {
            $ledger->amount = '1.00';
            $ledger->save();
            $this->fail('台账记录不允许更新');
        } catch (LedgerImmutableException $e) {
            $this->assertStringContainsString('只允许追加', $e->getMessage());
        }

        $this->assertSame('100.00', $ledger->fresh()->amount);

        try {
            $ledger->delete();
            $this->fail('台账记录不允许删除');
        } catch (LedgerImmutableException $e) {
            $this->assertStringContainsString('只允许追加', $e->getMessage());
        }

        $this->assertSame(1, MemberLedger::query()->count());
    }

    public function test_ledger_table_has_only_created_at_timestamp(): void
    {
        $this->assertTrue(Schema::hasColumn('member_ledger', 'created_at'));
        $this->assertFalse(Schema::hasColumn('member_ledger', 'updated_at'));

        $columns = [
            'member_id',
            'account_type',
            'direction',
            'amount',
            'biz_type',
            'biz_id',
            'idempotency_key',
            'balance_after',
            'operator_id',
        ];
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn('member_ledger', $column), '台账缺少字段：'.$column);
        }
    }
}
