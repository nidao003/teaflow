<?php

namespace Tests\Feature\Domain\Printing;

use App\Domain\Printing\Drivers\FakePrinterDriver;
use App\Domain\Printing\Exceptions\PrintRetryLimitExceededException;
use App\Domain\Printing\Models\PrintJob;
use App\Domain\Printing\Models\PrintTemplate;
use App\Domain\Printing\Services\PrintService;
use InvalidArgumentException;

class PrintServiceTest extends PrintingTestCase
{
    private FakePrinterDriver $driver;

    private PrintService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new FakePrinterDriver;
        $this->service = new PrintService($this->driver);
    }

    public function test_create_job_renders_template_and_persists_payload(): void
    {
        $template = $this->createTemplate();

        $job = $this->service->createJob($template, $this->templateVars(), [
            'store_id' => 5,
            'order_number' => 'T20261026001',
            'device_id' => 'printer-1',
        ]);

        $this->assertSame(PrintJob::STATUS_PENDING, $job->status);
        $this->assertSame(0, $job->retries);
        $this->assertSame(5, $job->store_id);
        $this->assertSame($template->id, $job->template_id);
        $this->assertSame('T20261026001', $job->order_number);
        $this->assertSame('printer-1', $job->device_id);
        $this->assertSame(PrintTemplate::TYPE_RECEIPT, $job->type);

        $content = $job->content();
        $this->assertStringContainsString('静安店', $content);
        $this->assertStringContainsString('订单号：T20261026001', $content);
        $this->assertStringContainsString('取餐码：A012', $content);
        $this->assertStringContainsString('金额：￥18.00', $content);
        $this->assertSame($this->templateVars(), $job->payload['vars']);

        $this->assertDatabaseHas('print_jobs', [
            'id' => $job->id,
            'store_id' => 5,
            'order_number' => 'T20261026001',
            'status' => 'pending',
            'retries' => 0,
        ]);
        // 创建任务不立即下发
        $this->assertSame(0, $this->driver->count());
    }

    public function test_process_success_marks_job_success_and_prints_once(): void
    {
        $template = $this->createTemplate();
        $job = $this->service->createJob($template, $this->templateVars(), ['store_id' => 5]);

        $printed = $this->service->process($job);

        $this->assertSame(PrintJob::STATUS_SUCCESS, $printed->status);
        $this->assertNotNull($printed->printed_at);
        $this->assertNull($printed->error);
        $this->assertSame(1, $this->driver->count());
        $this->assertSame($printed->content(), $this->driver->lastPayload());
        $this->assertDatabaseHas('print_jobs', ['id' => $job->id, 'status' => 'success']);
    }

    public function test_process_failure_records_error_and_retry_succeeds(): void
    {
        $this->driver->failWith('打印机缺纸');
        $template = $this->createTemplate();
        $job = $this->service->createJob($template, $this->templateVars(), ['store_id' => 5]);

        $failed = $this->service->process($job);

        $this->assertSame(PrintJob::STATUS_FAILED, $failed->status);
        $this->assertSame(1, $failed->retries);
        $this->assertSame('打印机缺纸', $failed->error);
        $this->assertNull($failed->printed_at);
        $this->assertSame(0, $this->driver->count());
        $this->assertDatabaseHas('print_jobs', ['id' => $job->id, 'status' => 'failed', 'retries' => 1]);

        $this->driver->succeed();
        $retried = $this->service->retry($failed);

        $this->assertSame(PrintJob::STATUS_SUCCESS, $retried->status);
        $this->assertSame(1, $retried->retries);
        $this->assertNull($retried->error);
        $this->assertNotNull($retried->printed_at);
        $this->assertSame(1, $this->driver->count());
    }

    public function test_retry_stops_at_max_attempts(): void
    {
        $this->driver->failWith('打印机离线');
        $service = new PrintService($this->driver, null, 2);
        $template = $this->createTemplate();
        $job = $service->createJob($template, $this->templateVars(), ['store_id' => 5]);

        $first = $service->process($job);
        $this->assertSame(1, $first->retries);

        $second = $service->retry($first);
        $this->assertSame(2, $second->retries);

        $this->expectException(PrintRetryLimitExceededException::class);

        $service->retry($second);
    }

    public function test_reprint_creates_new_pending_job_and_keeps_original(): void
    {
        $template = $this->createTemplate();
        $original = $this->service->createJob($template, $this->templateVars(), [
            'store_id' => 5,
            'order_number' => 'T20261026001',
            'device_id' => 'printer-1',
        ]);
        $this->service->process($original);
        $this->assertSame(PrintJob::STATUS_SUCCESS, $original->status);

        $copy = $this->service->reprint($original, 'printer-2');

        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame(PrintJob::STATUS_PENDING, $copy->status);
        $this->assertSame(0, $copy->retries);
        $this->assertSame('printer-2', $copy->device_id);
        $this->assertSame('T20261026001', $copy->order_number);
        $this->assertSame($template->id, $copy->template_id);
        $this->assertSame($original->content(), $copy->content());

        // 原任务历史保留且状态不变
        $this->assertSame(PrintJob::STATUS_SUCCESS, $original->fresh()->status);
        $this->assertSame('printer-1', $original->fresh()->device_id);

        // 补打任务可再次下发
        $reprinted = $this->service->process($copy);
        $this->assertSame(PrintJob::STATUS_SUCCESS, $reprinted->status);
        $this->assertSame(2, $this->driver->count());
        $this->assertDatabaseCount('print_jobs', 2);
    }

    public function test_process_already_success_job_throws(): void
    {
        $template = $this->createTemplate();
        $job = $this->service->createJob($template, $this->templateVars(), ['store_id' => 5]);
        $this->service->process($job);

        $this->expectException(InvalidArgumentException::class);

        $this->service->process($job->refresh());
    }

    public function test_dispatch_by_id(): void
    {
        $template = $this->createTemplate();
        $job = $this->service->createJob($template, $this->templateVars(), ['store_id' => 5]);

        $printed = $this->service->dispatch($job->id);

        $this->assertSame(PrintJob::STATUS_SUCCESS, $printed->status);
        $this->assertSame(1, $this->driver->count());
    }

    public function test_store_specific_template_cannot_be_used_by_another_store(): void
    {
        $template = $this->createTemplate(['store_id' => 5]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->createJob($template, $this->templateVars(), ['store_id' => 7]);
    }

    public function test_disabled_template_rejected(): void
    {
        $template = $this->createTemplate(['status' => PrintTemplate::STATUS_DISABLED]);

        $this->expectException(InvalidArgumentException::class);

        $this->service->createJob($template, $this->templateVars(), ['store_id' => 5]);
    }

    public function test_missing_template_variable_rejected(): void
    {
        $template = $this->createTemplate();
        $vars = $this->templateVars();
        unset($vars['order']);

        $this->expectException(InvalidArgumentException::class);

        $this->service->createJob($template, $vars, ['store_id' => 5]);
    }

    public function test_store_id_required(): void
    {
        $template = $this->createTemplate();

        $this->expectException(InvalidArgumentException::class);

        $this->service->createJob($template, $this->templateVars());
    }
}
