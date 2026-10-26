<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Hr;

use App\Domain\Hr\Enums\StaffStatus;
use App\Domain\Hr\Exceptions\StaffNotSchedulableException;
use App\Domain\Hr\Models\Staff;
use App\Domain\Hr\Services\StaffService;

class StaffServiceTest extends HrTestCase
{
    public function test_create_staff_defaults_to_active(): void
    {
        $staff = $this->app->make(StaffService::class)->createStaff([
            'store_id' => self::STORE_ID,
            'name' => '新员工',
            'role' => 'barista',
        ]);

        $this->assertSame('新员工', $staff->name);
        $this->assertSame(StaffStatus::ACTIVE, $staff->status);
        $this->assertSame(self::STORE_ID, $staff->store_id);
    }

    public function test_disable_and_enable_staff(): void
    {
        $staff = $this->createStaff();
        $service = $this->app->make(StaffService::class);

        $disabled = $service->disable((int) $staff->getKey());
        $this->assertSame(StaffStatus::DISABLED, $disabled->status);

        $enabled = $service->enable((int) $staff->getKey());
        $this->assertSame(StaffStatus::ACTIVE, $enabled->status);
    }

    public function test_disabled_staff_is_not_schedulable(): void
    {
        $staff = $this->createStaff(['status' => StaffStatus::DISABLED]);

        $this->expectException(StaffNotSchedulableException::class);
        $this->app->make(StaffService::class)->assertSchedulable((int) $staff->getKey(), self::STORE_ID);
    }

    public function test_list_by_store_filters_status(): void
    {
        $this->createStaff(['name' => '在职A']);
        $this->createStaff(['name' => '停用B', 'status' => StaffStatus::DISABLED]);

        $service = $this->app->make(StaffService::class);

        $this->assertCount(2, $service->listByStore(self::STORE_ID));
        $this->assertCount(1, $service->listByStore(self::STORE_ID, StaffStatus::ACTIVE));
        $this->assertSame('在职A', $service->listByStore(self::STORE_ID, StaffStatus::ACTIVE)->first()->name);
    }

    public function test_create_staff_requires_name_and_store(): void
    {
        $service = $this->app->make(StaffService::class);

        foreach ([
            ['store_id' => self::STORE_ID, 'name' => ''],
            ['name' => '无门店'],
        ] as $payload) {
            try {
                $service->createStaff($payload);
                $this->fail('非法员工数据应当被拒绝');
            } catch (\InvalidArgumentException) {
                $this->assertSame(0, Staff::query()->count());
            }
        }
    }
}
