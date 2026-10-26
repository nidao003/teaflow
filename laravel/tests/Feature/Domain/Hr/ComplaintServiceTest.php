<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Hr;

use App\Domain\Hr\Enums\ComplaintStatus;
use App\Domain\Hr\Enums\StaffStatus;
use App\Domain\Hr\Exceptions\ComplaintException;
use App\Domain\Hr\Exceptions\StaffNotSchedulableException;
use App\Domain\Hr\Models\Complaint;
use App\Domain\Hr\Services\ComplaintService;

class ComplaintServiceTest extends HrTestCase
{
    private ComplaintService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ComplaintService::class);
    }

    public function test_complaint_status_flow(): void
    {
        $handler = $this->createStaff(['name' => '处理人']);
        $complaint = $this->createComplaint();

        $this->assertSame(ComplaintStatus::PENDING, $complaint->status);

        $accepted = $this->service->accept((int) $complaint->getKey(), (int) $handler->getKey());
        $this->assertSame(ComplaintStatus::PROCESSING, $accepted->status);
        $this->assertSame($handler->getKey(), $accepted->handler_id);

        $resolved = $this->service->resolve((int) $complaint->getKey(), '已补偿优惠券');
        $this->assertSame(ComplaintStatus::RESOLVED, $resolved->status);
        $this->assertSame('已补偿优惠券', $resolved->resolution);
        $this->assertNotNull($resolved->handled_at);

        $closed = $this->service->close((int) $complaint->getKey());
        $this->assertSame(ComplaintStatus::CLOSED, $closed->status);
    }

    public function test_resolve_before_accept_is_rejected(): void
    {
        $complaint = $this->createComplaint();

        $this->expectException(ComplaintException::class);
        $this->service->resolve((int) $complaint->getKey());
    }

    public function test_close_before_resolve_is_rejected(): void
    {
        $handler = $this->createStaff();
        $complaint = $this->createComplaint();
        $this->service->accept((int) $complaint->getKey(), (int) $handler->getKey());

        $this->expectException(ComplaintException::class);
        $this->service->close((int) $complaint->getKey());
    }

    public function test_closed_complaint_cannot_be_reopened(): void
    {
        $handler = $this->createStaff();
        $complaint = $this->createComplaint();
        $this->service->accept((int) $complaint->getKey(), (int) $handler->getKey());
        $this->service->resolve((int) $complaint->getKey());
        $this->service->close((int) $complaint->getKey());

        $this->expectException(ComplaintException::class);
        $this->service->accept((int) $complaint->getKey(), (int) $handler->getKey());
    }

    public function test_accept_requires_active_staff(): void
    {
        $disabled = $this->createStaff(['status' => StaffStatus::DISABLED]);
        $complaint = $this->createComplaint();

        $this->expectException(StaffNotSchedulableException::class);
        $this->service->accept((int) $complaint->getKey(), (int) $disabled->getKey());
    }

    public function test_required_fields_are_validated(): void
    {
        try {
            $this->service->createComplaint(['store_id' => self::STORE_ID, 'type' => 'service', 'content' => '  ']);
            $this->fail('客诉内容不能为空');
        } catch (\InvalidArgumentException) {
            $this->assertSame(0, Complaint::query()->count());
        }
    }

    private function createComplaint(): Complaint
    {
        return $this->service->createComplaint([
            'store_id' => self::STORE_ID,
            'order_number' => 'TF202610260001',
            'type' => 'service',
            'content' => '高峰期等待时间过长。',
        ]);
    }
}
