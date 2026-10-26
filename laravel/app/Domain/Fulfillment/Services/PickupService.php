<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Enums\PickupStatus;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Exceptions\InvalidStateTransitionException;
use App\Domain\Fulfillment\Models\Pickup;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 自提服务：一单一自提，状态流转 pending → ready → picked，pending/ready 可取消。
 *
 * 状态更新均带 status 条件（CAS），并发流转只有一个请求生效。
 */
class PickupService
{
    /**
     * 创建自提单（order_number 唯一）。
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Pickup
    {
        $orderNumber = trim((string) ($data['order_number'] ?? ''));
        if ($orderNumber === '') {
            throw new FulfillmentException('缺少 order_number');
        }

        $storeId = (int) ($data['store_id'] ?? 0);
        if ($storeId <= 0) {
            throw new FulfillmentException('缺少 store_id');
        }

        $pickupNo = trim((string) ($data['pickup_no'] ?? ''));

        return DB::transaction(function () use ($orderNumber, $storeId, $pickupNo): Pickup {
            if (Pickup::query()->where('order_number', $orderNumber)->exists()) {
                throw new FulfillmentException('自提单已存在：'.$orderNumber);
            }

            try {
                return Pickup::create([
                    'order_number' => $orderNumber,
                    'pickup_no' => $pickupNo !== '' ? $pickupNo : $this->generatePickupNo(),
                    'store_id' => $storeId,
                    'status' => PickupStatus::PENDING,
                ]);
            } catch (QueryException $exception) {
                throw new FulfillmentException('自提单已存在（并发创建冲突）：'.$orderNumber, 0, $exception);
            }
        });
    }

    /**
     * 制作完成，可取餐：pending → ready（已是 ready 时幂等返回）。
     */
    public function markReady(Pickup|int $pickup): Pickup
    {
        $pickup = $this->resolve($pickup);

        return DB::transaction(function () use ($pickup): Pickup {
            $current = Pickup::query()->whereKey($pickup->id)->lockForUpdate()->firstOrFail();
            $status = $current->status;

            if ($status === PickupStatus::READY) {
                return $current;
            }

            $this->transition($current, $status, PickupStatus::READY, ['ready_at' => Carbon::now()]);

            return $current->refresh();
        });
    }

    /**
     * 顾客取餐：ready → picked。
     */
    public function markPicked(Pickup|int $pickup): Pickup
    {
        $pickup = $this->resolve($pickup);

        return DB::transaction(function () use ($pickup): Pickup {
            $current = Pickup::query()->whereKey($pickup->id)->lockForUpdate()->firstOrFail();

            $this->transition($current, $current->status, PickupStatus::PICKED, ['picked_at' => Carbon::now()]);

            return $current->refresh();
        });
    }

    /**
     * 取消自提：pending / ready → cancelled。
     */
    public function cancel(Pickup|int $pickup): Pickup
    {
        $pickup = $this->resolve($pickup);

        return DB::transaction(function () use ($pickup): Pickup {
            $current = Pickup::query()->whereKey($pickup->id)->lockForUpdate()->firstOrFail();

            $this->transition($current, $current->status, PickupStatus::CANCELLED, ['cancelled_at' => Carbon::now()]);

            return $current->refresh();
        });
    }

    public function find(string $orderNumber): ?Pickup
    {
        return Pickup::query()->where('order_number', trim($orderNumber))->first();
    }

    // ---------------------------------------------------------------------
    // 内部实现
    // ---------------------------------------------------------------------

    private function resolve(Pickup|int $pickup): Pickup
    {
        if ($pickup instanceof Pickup) {
            return $pickup;
        }

        $model = Pickup::query()->whereKey($pickup)->first();

        if ($model === null) {
            throw new FulfillmentException('自提单不存在：'.$pickup);
        }

        return $model;
    }

    /**
     * 状态流转（CAS）：status 不匹配即拒绝，保证并发只成功一次。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function transition(Pickup $pickup, PickupStatus $from, PickupStatus $to, array $attributes): void
    {
        if (! $from->canTransitTo($to)) {
            throw InvalidStateTransitionException::for('自提单', $from->value, $to->value);
        }

        $affected = Pickup::query()
            ->whereKey($pickup->id)
            ->where('status', $from->value)
            ->update(array_merge([
                'status' => $to->value,
                'updated_at' => Carbon::now(),
            ], $attributes));

        if ($affected === 0) {
            throw InvalidStateTransitionException::for('自提单', $from->value, $to->value);
        }
    }

    private function generatePickupNo(): string
    {
        do {
            $pickupNo = 'P'.Carbon::now()->format('md').str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Pickup::query()->where('pickup_no', $pickupNo)->exists());

        return $pickupNo;
    }
}
