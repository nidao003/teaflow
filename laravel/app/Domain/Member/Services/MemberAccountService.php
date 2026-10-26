<?php

declare(strict_types=1);

namespace App\Domain\Member\Services;

use App\Domain\Member\Enums\AccountType;
use App\Domain\Member\Enums\LedgerBizType;
use App\Domain\Member\Enums\LedgerDirection;
use App\Domain\Member\Exceptions\InsufficientBalanceException;
use App\Domain\Member\Models\MemberAccount;
use App\Domain\Member\Models\MemberLedger;
use App\Domain\Member\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 会员账户服务：统一处理储值/积分账户的开户与入账、出账。
 *
 * 一致性保证：
 * 1. 所有余额变动都在 DB::transaction 内完成；
 * 2. 变动前对 member_accounts 行加 lockForUpdate，串行化同一账户的并发扣减；
 * 3. idempotency_key 唯一，重复请求直接返回历史台账，不重复入账；
 * 4. 出账后余额不得为负，否则抛 InsufficientBalanceException 并回滚。
 */
class MemberAccountService
{
    public function openAccount(int $memberId, string $accountType = AccountType::BALANCE): MemberAccount
    {
        AccountType::assertValid($accountType);

        return DB::transaction(function () use ($memberId, $accountType): MemberAccount {
            return MemberAccount::query()->firstOrCreate(
                ['member_id' => $memberId, 'account_type' => $accountType],
                ['balance' => Money::fromCents(0), 'frozen' => Money::fromCents(0), 'version' => 0]
            );
        });
    }

    public function findAccount(int $memberId, string $accountType = AccountType::BALANCE): ?MemberAccount
    {
        AccountType::assertValid($accountType);

        return MemberAccount::query()
            ->where('member_id', $memberId)
            ->where('account_type', $accountType)
            ->first();
    }

    /**
     * 余额为两位小数字符串；账户不存在时视为 0.00。
     */
    public function balance(int $memberId, string $accountType = AccountType::BALANCE): string
    {
        $account = $this->findAccount($memberId, $accountType);

        return $account === null ? Money::fromCents(0) : (string) $account->balance;
    }

    public function recharge(
        int $memberId,
        int|float|string $amount,
        string $idempotencyKey,
        ?string $bizId = null,
        ?int $operatorId = null
    ): MemberLedger {
        return $this->apply(
            $memberId,
            AccountType::BALANCE,
            LedgerDirection::IN,
            $amount,
            LedgerBizType::RECHARGE,
            $idempotencyKey,
            $bizId,
            $operatorId
        );
    }

    public function consume(
        int $memberId,
        int|float|string $amount,
        string $idempotencyKey,
        ?string $bizId = null,
        ?int $operatorId = null
    ): MemberLedger {
        return $this->apply(
            $memberId,
            AccountType::BALANCE,
            LedgerDirection::OUT,
            $amount,
            LedgerBizType::CONSUME,
            $idempotencyKey,
            $bizId,
            $operatorId
        );
    }

    public function refund(
        int $memberId,
        int|float|string $amount,
        string $idempotencyKey,
        ?string $bizId = null,
        ?int $operatorId = null
    ): MemberLedger {
        return $this->apply(
            $memberId,
            AccountType::BALANCE,
            LedgerDirection::IN,
            $amount,
            LedgerBizType::REFUND,
            $idempotencyKey,
            $bizId,
            $operatorId
        );
    }

    /**
     * 人工调账：direction 为 in 表示补入，out 表示扣减。
     */
    public function adjust(
        int $memberId,
        int|float|string $amount,
        string $direction,
        string $idempotencyKey,
        ?string $bizId = null,
        ?int $operatorId = null,
        string $accountType = AccountType::BALANCE
    ): MemberLedger {
        return $this->apply(
            $memberId,
            $accountType,
            $direction,
            $amount,
            LedgerBizType::ADJUST,
            $idempotencyKey,
            $bizId,
            $operatorId
        );
    }

    /**
     * 台账 + 余额的原子记账入口。
     */
    public function apply(
        int $memberId,
        string $accountType,
        string $direction,
        int|float|string $amount,
        string $bizType,
        string $idempotencyKey,
        ?string $bizId = null,
        ?int $operatorId = null
    ): MemberLedger {
        AccountType::assertValid($accountType);
        LedgerDirection::assertValid($direction);
        LedgerBizType::assertValid($bizType);

        $key = trim($idempotencyKey);
        if ($key === '') {
            throw new InvalidArgumentException('idempotency_key 不能为空');
        }

        $amountCents = Money::toCents($amount);
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('记账金额必须大于 0');
        }

        try {
            return DB::transaction(function () use (
                $memberId,
                $accountType,
                $direction,
                $amountCents,
                $bizType,
                $key,
                $bizId,
                $operatorId
            ): MemberLedger {
                $existing = $this->findByIdempotencyKey($key);
                if ($existing !== null) {
                    return $existing;
                }

                $account = $this->lockAccount($memberId, $accountType);
                $currentCents = Money::toCents((string) $account->balance);
                $nextCents = $direction === LedgerDirection::IN
                    ? $currentCents + $amountCents
                    : $currentCents - $amountCents;

                if ($nextCents < 0) {
                    throw InsufficientBalanceException::forAccount(
                        $accountType,
                        Money::fromCents($amountCents),
                        Money::fromCents($currentCents)
                    );
                }

                $ledger = MemberLedger::query()->create([
                    'member_id' => $memberId,
                    'account_type' => $accountType,
                    'direction' => $direction,
                    'amount' => Money::fromCents($amountCents),
                    'biz_type' => $bizType,
                    'biz_id' => $bizId,
                    'idempotency_key' => $key,
                    'balance_after' => Money::fromCents($nextCents),
                    'operator_id' => $operatorId,
                ]);

                $account->fill([
                    'balance' => Money::fromCents($nextCents),
                    'version' => ((int) $account->version) + 1,
                ])->save();

                return $ledger;
            });
        } catch (QueryException $e) {
            // 并发下同一 idempotency_key 同时落库，唯一索引兜底：返回已存在台账，不重复入账。
            if ($this->isUniqueViolation($e)) {
                $existing = $this->findByIdempotencyKey($key);
                if ($existing !== null) {
                    return $existing;
                }
            }

            throw $e;
        }
    }

    protected function findByIdempotencyKey(string $key): ?MemberLedger
    {
        return MemberLedger::query()->where('idempotency_key', $key)->first();
    }

    /**
     * 行锁读取账户；不存在时先开户再锁读。
     */
    protected function lockAccount(int $memberId, string $accountType): MemberAccount
    {
        $query = static fn () => MemberAccount::query()
            ->where('member_id', $memberId)
            ->where('account_type', $accountType);

        $account = $query()->lockForUpdate()->first();
        if ($account !== null) {
            return $account;
        }

        MemberAccount::query()->firstOrCreate(
            ['member_id' => $memberId, 'account_type' => $accountType],
            ['balance' => Money::fromCents(0), 'frozen' => Money::fromCents(0), 'version' => 0]
        );

        return $query()->lockForUpdate()->firstOrFail();
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000'
            || $driverCode === 1062
            || $driverCode === 19
            || str_contains($e->getMessage(), 'UNIQUE constraint failed')
            || str_contains($e->getMessage(), 'Duplicate entry');
    }
}
