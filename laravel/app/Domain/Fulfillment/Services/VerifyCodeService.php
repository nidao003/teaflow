<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Enums\VerifyCodeStatus;
use App\Domain\Fulfillment\Enums\VerifyCodeType;
use App\Domain\Fulfillment\Exceptions\CrossStoreOperationException;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Exceptions\InvalidStateTransitionException;
use App\Domain\Fulfillment\Exceptions\VerifyCodeExpiredException;
use App\Domain\Fulfillment\Exceptions\VerifyCodeNotFoundException;
use App\Domain\Fulfillment\Exceptions\VerifyCodeUsedException;
use App\Domain\Fulfillment\Models\VerifyCode;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 核销码服务：签发、一次性核销、过期/作废。
 *
 * 一致性保证：
 * 1. 核销在 DB::transaction 内对 verify_codes 行 lockForUpdate，串行化同一码的并发核销；
 * 2. 状态更新带 status=issued 条件（CAS），并发时只有一个请求能把码改成 used；
 * 3. 跨店核销、过期、已用、作废均显式拒绝，且拒绝时不改状态；
 * 4. 过期判断以服务端时间与 expires_at 为准，不信任客户端时间。
 */
class VerifyCodeService
{
    /**
     * 签发核销码（默认 2 小时有效，可指定过期时间）。
     *
     * @param  array<string, mixed>  $data
     */
    public function issue(array $data): VerifyCode
    {
        $orderNumber = trim((string) ($data['order_number'] ?? ''));
        if ($orderNumber === '') {
            throw new FulfillmentException('缺少 order_number');
        }

        $storeId = (int) ($data['store_id'] ?? 0);
        if ($storeId <= 0) {
            throw new FulfillmentException('缺少 store_id');
        }

        $type = VerifyCodeType::fromValue($data['type'] ?? null);
        $expiresAt = $this->parseDate($data['expires_at'] ?? null) ?? Carbon::now()->addHours(2);

        if ($expiresAt->isPast()) {
            throw new FulfillmentException('过期时间不能早于当前时间');
        }

        return DB::transaction(fn (): VerifyCode => VerifyCode::create([
            'code' => $this->generateCode(),
            'order_number' => $orderNumber,
            'store_id' => $storeId,
            'type' => $type,
            'status' => VerifyCodeStatus::ISSUED,
            'expires_at' => $expiresAt,
        ]));
    }

    /**
     * 一次性核销：issued → used，仅限发码门店，过期/已用/作废拒绝。
     */
    public function use(string $code, int $storeId, ?int $operatorId = null, ?string $orderNumber = null): VerifyCode
    {
        if ($storeId <= 0) {
            throw new FulfillmentException('缺少核销门店 store_id');
        }

        $normalized = strtoupper(trim($code));

        try {
            return $this->useInTransaction($normalized, $storeId, $operatorId, $orderNumber);
        } catch (VerifyCodeExpiredException $exception) {
            // 过期拒绝时仍要把 issued 的码落库为 expired（事务已回滚，独立补记）
            $this->markExpiredQuietly($normalized);

            throw $exception;
        }
    }

    /**
     * @throws VerifyCodeNotFoundException
     * @throws CrossStoreOperationException
     * @throws VerifyCodeUsedException
     * @throws VerifyCodeExpiredException
     */
    private function useInTransaction(string $normalized, int $storeId, ?int $operatorId, ?string $orderNumber): VerifyCode
    {
        return DB::transaction(function () use ($normalized, $storeId, $operatorId, $orderNumber): VerifyCode {
            $verifyCode = VerifyCode::query()
                ->where('code', $normalized)
                ->lockForUpdate()
                ->first();

            if ($verifyCode === null) {
                throw VerifyCodeNotFoundException::forCode($normalized);
            }

            // 跨店拒绝：核销码只在发码门店可用
            if ($verifyCode->store_id !== $storeId) {
                throw CrossStoreOperationException::for('核销码 '.$normalized, $storeId, $verifyCode->store_id);
            }

            $status = $verifyCode->status;

            if ($status === VerifyCodeStatus::USED) {
                throw VerifyCodeUsedException::forCode($normalized, $verifyCode->used_at?->toDateTimeString());
            }

            if ($status === VerifyCodeStatus::VOID) {
                throw new FulfillmentException('核销码已作废：'.$normalized);
            }

            if ($status === VerifyCodeStatus::EXPIRED) {
                throw VerifyCodeExpiredException::forCode($normalized, $verifyCode->expires_at?->toDateTimeString());
            }

            if ($verifyCode->isExpired()) {
                throw VerifyCodeExpiredException::forCode($normalized, $verifyCode->expires_at?->toDateTimeString());
            }

            if ($orderNumber !== null && $orderNumber !== '' && $verifyCode->order_number !== trim($orderNumber)) {
                throw new FulfillmentException('核销码与订单不匹配：'.$normalized);
            }

            $now = Carbon::now();
            $affected = VerifyCode::query()
                ->whereKey($verifyCode->id)
                ->where('status', VerifyCodeStatus::ISSUED->value)
                ->update([
                    'status' => VerifyCodeStatus::USED->value,
                    'used_at' => $now,
                    'used_by' => $operatorId,
                    'updated_at' => $now,
                ]);

            if ($affected === 0) {
                // 条件更新失败说明已被并发核销
                throw VerifyCodeUsedException::forCode($normalized);
            }

            return $verifyCode->refresh();
        });
    }

    /**
     * 手动置为过期（仅 issued 可流转；已过期直接返回）。
     */
    public function expire(VerifyCode|string $verifyCode): VerifyCode
    {
        return DB::transaction(function () use ($verifyCode): VerifyCode {
            $current = $this->resolve($verifyCode);
            $current = VerifyCode::query()->whereKey($current->id)->lockForUpdate()->firstOrFail();
            $status = $current->status;

            if ($status === VerifyCodeStatus::EXPIRED) {
                return $current;
            }

            if (! $status->canTransitTo(VerifyCodeStatus::EXPIRED)) {
                throw InvalidStateTransitionException::for('核销码', $status->value, VerifyCodeStatus::EXPIRED->value);
            }

            $this->markExpired($current);

            return $current->refresh();
        });
    }

    /**
     * 作废核销码（仅 issued 可流转）。
     */
    public function void(VerifyCode|string $verifyCode): VerifyCode
    {
        return DB::transaction(function () use ($verifyCode): VerifyCode {
            $current = $this->resolve($verifyCode);
            $current = VerifyCode::query()->whereKey($current->id)->lockForUpdate()->firstOrFail();
            $status = $current->status;

            if (! $status->canTransitTo(VerifyCodeStatus::VOID)) {
                throw InvalidStateTransitionException::for('核销码', $status->value, VerifyCodeStatus::VOID->value);
            }

            $now = Carbon::now();
            $affected = VerifyCode::query()
                ->whereKey($current->id)
                ->where('status', $status->value)
                ->update(['status' => VerifyCodeStatus::VOID->value, 'updated_at' => $now]);

            if ($affected === 0) {
                throw InvalidStateTransitionException::for('核销码', $status->value, VerifyCodeStatus::VOID->value);
            }

            return $current->refresh();
        });
    }

    /**
     * 批量把已过期的 issued 码置为 expired，返回处理条数。
     */
    public function expireDue(?CarbonInterface $now = null): int
    {
        $now ??= Carbon::now();

        return VerifyCode::query()
            ->where('status', VerifyCodeStatus::ISSUED->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->update([
                'status' => VerifyCodeStatus::EXPIRED->value,
                'updated_at' => Carbon::now(),
            ]);
    }

    public function find(string $code): ?VerifyCode
    {
        return VerifyCode::query()->where('code', strtoupper(trim($code)))->first();
    }

    // ---------------------------------------------------------------------
    // 内部实现
    // ---------------------------------------------------------------------

    private function resolve(VerifyCode|string $verifyCode): VerifyCode
    {
        if ($verifyCode instanceof VerifyCode) {
            return $verifyCode;
        }

        $normalized = strtoupper(trim($verifyCode));
        $model = VerifyCode::query()->where('code', $normalized)->first();

        if ($model === null) {
            throw VerifyCodeNotFoundException::forCode($normalized);
        }

        return $model;
    }

    private function markExpired(VerifyCode $verifyCode): void
    {
        $verifyCode->forceFill([
            'status' => VerifyCodeStatus::EXPIRED,
            'updated_at' => Carbon::now(),
        ])->save();
    }

    /**
     * 独立事务外的过期补记：只把 issued 的码置为 expired，不覆盖已用/已作废状态。
     */
    private function markExpiredQuietly(string $code): void
    {
        VerifyCode::query()
            ->where('code', $code)
            ->where('status', VerifyCodeStatus::ISSUED->value)
            ->update([
                'status' => VerifyCodeStatus::EXPIRED->value,
                'updated_at' => Carbon::now(),
            ]);
    }

    private function generateCode(): string
    {
        do {
            $code = 'VC'.strtoupper(Str::random(8));
        } while (VerifyCode::query()->where('code', $code)->exists());

        return $code;
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value);
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable $exception) {
            throw new FulfillmentException('expires_at 格式非法：'.(string) $value, 0, $exception);
        }
    }
}
