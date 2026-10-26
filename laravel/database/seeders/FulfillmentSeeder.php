<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Fulfillment\Models\Pickup;
use App\Domain\Fulfillment\Models\ReservationSlot;
use App\Domain\Fulfillment\Services\PickupService;
use App\Domain\Fulfillment\Services\ReservationService;
use Illuminate\Database\Seeder;

/**
 * 核销/自提/预约域最小演示数据：门店 1 未来 5 天各 3 个预约时段 + 1 条自提单。
 * 幂等：重复执行不会重复建时段/自提单。
 */
class FulfillmentSeeder extends Seeder
{
    public function run(): void
    {
        /** @var ReservationService $reservations */
        $reservations = app(ReservationService::class);

        $windows = [
            ['17:00', '18:00', 20],
            ['18:00', '19:00', 20],
            ['19:00', '20:00', 20],
        ];

        foreach (range(1, 5) as $offset) {
            $date = now()->addDays($offset)->toDateString();

            foreach ($windows as [$startTime, $endTime, $capacity]) {
                $exists = ReservationSlot::query()
                    ->where('store_id', 1)
                    ->where('date', $date)
                    ->where('start_time', $startTime)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $reservations->createSlot([
                    'store_id' => 1,
                    'date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'capacity' => $capacity,
                ]);
            }
        }

        /** @var PickupService $pickups */
        $pickups = app(PickupService::class);

        if (! Pickup::query()->where('order_number', 'SEED-ORDER-0001')->exists()) {
            $pickups->create([
                'order_number' => 'SEED-ORDER-0001',
                'store_id' => 1,
                'pickup_no' => 'P0001',
            ]);
        }
    }
}
