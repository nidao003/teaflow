<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment\Services;

use App\Domain\Fulfillment\Enums\ReservationSlotStatus;
use App\Domain\Fulfillment\Enums\ReservationStatus;
use App\Domain\Fulfillment\Exceptions\CapacityExceededException;
use App\Domain\Fulfillment\Exceptions\ConcurrentCapacityUpdateException;
use App\Domain\Fulfillment\Exceptions\FulfillmentException;
use App\Domain\Fulfillment\Exceptions\InvalidStateTransitionException;
use App\Domain\Fulfillment\Models\Reservation;
use App\Domain\Fulfillment\Models\ReservationSlot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 预约服务：时段创建、预约、到店、转单、取消、爽约。
 *
 * 容量防超卖：
 * 1. book 在 DB::transaction 内对 reservation_slots 行 lockForUpdate 串行化；
 * 2. booked_count 用条件更新（where booked_count = 旧值）做 CAS，冲突则有界重试；
 * 3. 只有 booked_count + party_size <= capacity 才允许写入，容量不足抛 CapacityExceededException；
 * 4. cancel / noShow 释放容量，converted 占用容量不回退。
 */
class ReservationService
{
    /** 容量并发冲突时的最大重试次数。 */
    protected int $maxAttempts = 3;

    /**
     * 创建预约时段（同一门店同一天同开始时间唯一）。
     *
     * @param  array<string, mixed>  $data
     */
    public function createSlot(array $data): ReservationSlot
    {
        $storeId = (int) ($data['store_id'] ?? 0);
        if ($storeId <= 0) {
            throw new FulfillmentException('缺少 store_id');
        }

        $date = $this->dateOrFail($data['date'] ?? null);
        $startTime = $this->timeOrFail($data['start_time'] ?? null, 'start_time');
        $endTime = $this->timeOrFail($data['end_time'] ?? null, 'end_time');

        if ($endTime <= $startTime) {
            throw new FulfillmentException('结束时间必须晚于开始时间');
        }

        $capacity = (int) ($data['capacity'] ?? 0);
        if ($capacity <= 0) {
            throw new FulfillmentException('容量必须大于 0');
        }

        $status = ReservationSlotStatus::fromValue($data['status'] ?? null);

        return DB::transaction(function () use ($storeId, $date, $startTime, $endTime, $capacity, $status): ReservationSlot {
            $exists = ReservationSlot::query()
                ->where('store_id', $storeId)
                ->where('date', $date)
                ->where('start_time', $startTime)
                ->exists();

            if ($exists) {
                throw new FulfillmentException(sprintf(
                    '预约时段已存在：store=%d date=%s start=%s',
                    $storeId,
                    $date,
                    $startTime
                ));
            }

            try {
                return ReservationSlot::create([
                    'store_id' => $storeId,
                    'date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'capacity' => $capacity,
                    'booked_count' => 0,
                    'status' => $status,
                ]);
            } catch (QueryException $exception) {
                throw new FulfillmentException('预约时段已存在（并发创建冲突）', 0, $exception);
            }
        });
    }

    /**
     * 预约时段（容量锁防超卖）。
     *
     * @param  array<string, mixed>  $data
     */
    public function book(array $data): Reservation
    {
        $slotId = (int) ($data['slot_id'] ?? 0);
        if ($slotId <= 0) {
            throw new FulfillmentException('缺少 slot_id');
        }

        $partySize = (int) ($data['party_size'] ?? 0);
        if ($partySize <= 0) {
            throw new FulfillmentException('预约人数必须大于 0');
        }

        $memberId = isset($data['member_id']) && $data['member_id'] !== null ? (int) $data['member_id'] : null;
        $note = isset($data['note']) && $data['note'] !== null ? (string) $data['note'] : null;

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->maxAttempts) {
            $attempt++;

            try {
                return DB::transaction(function () use ($slotId, $partySize, $memberId, $note): Reservation {
                    $slot = ReservationSlot::query()->whereKey($slotId)->lockForUpdate()->first();

                    if ($slot === null) {
                        throw new FulfillmentException('预约时段不存在：'.$slotId);
                    }

                    if (! $slot->isActive()) {
                        throw new FulfillmentException('预约时段已关闭：'.$slotId);
                    }

                    $booked = $slot->booked_count;

                    if ($booked + $partySize > $slot->capacity) {
                        throw CapacityExceededException::forSlot($slot->id, $slot->capacity, $booked, $partySize);
                    }

                    if (! $this->persistBookedCount($slot, $booked, $booked + $partySize)) {
                        throw ConcurrentCapacityUpdateException::forSlot($slot->id);
                    }

                    return Reservation::create([
                        'member_id' => $memberId,
                        'store_id' => $slot->store_id,
                        'slot_id' => $slot->id,
                        'party_size' => $partySize,
                        'status' => ReservationStatus::BOOKED,
                        'note' => $note,
                    ]);
                });
            } catch (ConcurrentCapacityUpdateException $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException ?? new FulfillmentException('预约容量更新失败');
    }

    /**
     * 到店：booked → arrived。
     */
    public function arrive(Reservation|int $reservation): Reservation
    {
        return $this->applyTransition($reservation, ReservationStatus::ARRIVED, [
            'arrived_at' => Carbon::now(),
        ]);
    }

    /**
     * 转单：arrived → converted，order_number 只写一次（二次转单拒绝，不复写订单号）。
     */
    public function convert(Reservation|int $reservation, string $orderNumber): Reservation
    {
        $orderNumber = trim($orderNumber);
        if ($orderNumber === '') {
            throw new FulfillmentException('转单必须提供 order_number');
        }

        return $this->applyTransition($reservation, ReservationStatus::CONVERTED, [
            'order_number' => $orderNumber,
            'converted_at' => Carbon::now(),
        ]);
    }

    /**
     * 取消预约：booked → cancelled，并释放容量。
     */
    public function cancel(Reservation|int $reservation): Reservation
    {
        return $this->applyTransition($reservation, ReservationStatus::CANCELLED, [
            'cancelled_at' => Carbon::now(),
        ], true);
    }

    /**
     * 爽约：booked → no_show，并释放容量。
     */
    public function noShow(Reservation|int $reservation): Reservation
    {
        return $this->applyTransition($reservation, ReservationStatus::NO_SHOW, [
            'no_show_at' => Carbon::now(),
        ], true);
    }

    // ---------------------------------------------------------------------
    // 内部实现
    // ---------------------------------------------------------------------

    /**
     * 状态流转（CAS）+ 可选释放容量。
     *
     * @param  array<string, mixed>  $attributes
     */
    private function applyTransition(
        Reservation|int $reservation,
        ReservationStatus $to,
        array $attributes = [],
        bool $releaseCapacity = false
    ): Reservation {
        $reservation = $this->resolveReservation($reservation);

        return DB::transaction(function () use ($reservation, $to, $attributes, $releaseCapacity): Reservation {
            $current = Reservation::query()->whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $from = $current->status;

            if (! $from->canTransitTo($to)) {
                throw InvalidStateTransitionException::for('预约单', $from->value, $to->value);
            }

            $affected = Reservation::query()
                ->whereKey($current->id)
                ->where('status', $from->value)
                ->update(array_merge([
                    'status' => $to->value,
                    'updated_at' => Carbon::now(),
                ], $attributes));

            if ($affected === 0) {
                throw InvalidStateTransitionException::for('预约单', $from->value, $to->value);
            }

            if ($releaseCapacity) {
                $this->releaseCapacity($current->slot_id, $current->party_size);
            }

            return $current->refresh();
        });
    }

    /**
     * 条件更新已预约人数（CAS）：返回 false 表示被并发修改，由调用方重试。
     */
    protected function persistBookedCount(ReservationSlot $slot, int $expected, int $newCount): bool
    {
        $affected = ReservationSlot::query()
            ->whereKey($slot->id)
            ->where('booked_count', $expected)
            ->update([
                'booked_count' => $newCount,
                'updated_at' => Carbon::now(),
            ]);

        return $affected > 0;
    }

    /**
     * 释放容量（取消/爽约）。
     */
    private function releaseCapacity(int $slotId, int $partySize): void
    {
        $slot = ReservationSlot::query()->whereKey($slotId)->lockForUpdate()->first();

        if ($slot === null) {
            return;
        }

        $newCount = max(0, $slot->booked_count - $partySize);

        ReservationSlot::query()
            ->whereKey($slot->id)
            ->update(['booked_count' => $newCount, 'updated_at' => Carbon::now()]);
    }

    private function resolveReservation(Reservation|int $reservation): Reservation
    {
        $id = $reservation instanceof Reservation ? (int) $reservation->getKey() : $reservation;
        $model = Reservation::query()->whereKey($id)->first();

        if ($model === null) {
            throw new FulfillmentException('预约单不存在：'.$id);
        }

        return $model;
    }

    private function dateOrFail(mixed $value): string
    {
        if ($value === null || $value === '') {
            throw new FulfillmentException('缺少 date');
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable $exception) {
            throw new FulfillmentException('date 格式非法：'.(string) $value, 0, $exception);
        }
    }

    private function timeOrFail(mixed $value, string $field): string
    {
        if ($value === null || $value === '') {
            throw new FulfillmentException('缺少 '.$field);
        }

        $raw = trim((string) $value);

        try {
            return Carbon::createFromFormat('H:i', $raw)->format('H:i');
        } catch (\Throwable) {
            try {
                return Carbon::parse($raw)->format('H:i');
            } catch (\Throwable $exception) {
                throw new FulfillmentException($field.' 格式非法：'.$raw, 0, $exception);
            }
        }
    }
}
