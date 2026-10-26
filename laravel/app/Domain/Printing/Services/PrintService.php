<?php

namespace App\Domain\Printing\Services;

use App\Domain\Printing\Contracts\PrinterDriver;
use App\Domain\Printing\Drivers\EscPosPrinterDriver;
use App\Domain\Printing\Exceptions\PrintRetryLimitExceededException;
use App\Domain\Printing\Models\PrintJob;
use App\Domain\Printing\Models\PrintTemplate;
use App\Domain\Printing\Support\TemplateRenderer;
use Carbon\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * 打印服务：模板渲染 → 创建打印任务 → 下发（单次尝试）→ 失败重试 → 补打。
 *
 * 约定：
 * - 打印失败不向上抛异常，而是把 error/retries 落库（failed 状态），由调用方决定 retry；
 * - 已成功的任务禁止再次 process，补打必须通过 reprint() 生成新任务（保留历史可审计）；
 * - 驱动通过构造注入，测试/自定义设备用 FakePrinterDriver 或 EscPosPrinterDriver::file()/network()。
 */
class PrintService
{
    private PrinterDriver $driver;

    private TemplateRenderer $renderer;

    private int $maxAttempts;

    public function __construct(
        ?PrinterDriver $driver = null,
        ?TemplateRenderer $renderer = null,
        int $maxAttempts = 3,
    ) {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('maxAttempts 必须为正整数');
        }

        $this->driver = $driver ?? new EscPosPrinterDriver;
        $this->renderer = $renderer ?? new TemplateRenderer;
        $this->maxAttempts = $maxAttempts;
    }

    /**
     * 渲染模板内容（{{var}} / {{ nested.key }}）。
     */
    public function render(PrintTemplate|string $template, array $vars = []): string
    {
        if ($template instanceof PrintTemplate) {
            if ((int) $template->status !== PrintTemplate::STATUS_ACTIVE) {
                throw new InvalidArgumentException("打印模板 {$template->id} 已停用");
            }

            return $this->renderer->render($template->content, $vars);
        }

        return $this->renderer->render($template, $vars);
    }

    /**
     * 渲染并创建待打印任务；payload 落库为 {content, vars}。
     *
     * @param  array{
     *     store_id?: int|string,
     *     order_number?: string|null,
     *     device_id?: string|null,
     *     type?: string|null
     * }  $attributes
     */
    public function createJob(PrintTemplate|string $template, array $vars = [], array $attributes = []): PrintJob
    {
        $storeId = (int) ($attributes['store_id'] ?? 0);
        if ($storeId <= 0) {
            throw new InvalidArgumentException('store_id 必须为正整数');
        }

        $templateModel = $template instanceof PrintTemplate ? $template : null;

        if ($templateModel !== null && $templateModel->store_id !== null && (int) $templateModel->store_id !== $storeId) {
            throw new InvalidArgumentException(
                "打印模板 {$templateModel->id} 属于门店 {$templateModel->store_id}，不能用于门店 {$storeId}"
            );
        }

        $content = $this->render($template, $vars);

        $type = (string) ($attributes['type'] ?? $templateModel?->type ?? PrintTemplate::TYPE_RECEIPT);
        if (! in_array($type, PrintTemplate::TYPES, true)) {
            throw new InvalidArgumentException("不支持的打印类型「{$type}」");
        }

        $orderNumber = $this->nullableString($attributes['order_number'] ?? null);
        $deviceId = $this->nullableString($attributes['device_id'] ?? null);

        return PrintJob::create([
            'store_id' => $storeId,
            'order_number' => $orderNumber,
            'template_id' => $templateModel?->id,
            'device_id' => $deviceId,
            'type' => $type,
            'payload' => [
                'content' => $content,
                'vars' => $vars,
            ],
            'status' => PrintJob::STATUS_PENDING,
            'retries' => 0,
        ])->refresh();
    }

    /**
     * 按 id 或实例下发一次打印。
     */
    public function dispatch(PrintJob|int $job): PrintJob
    {
        return $this->process($job instanceof PrintJob ? $job : $this->findJob($job));
    }

    /**
     * 单次打印尝试：成功 → success + printed_at；失败 → failed + error/retries。
     */
    public function process(PrintJob $job): PrintJob
    {
        if ($job->isSuccess()) {
            throw new InvalidArgumentException("打印任务 {$job->id} 已打印成功，如需补打请使用 reprint()");
        }

        $job->status = PrintJob::STATUS_PRINTING;
        $job->error = null;
        $job->save();

        try {
            $this->driver->print($job->content());
        } catch (Throwable $e) {
            $job->status = PrintJob::STATUS_FAILED;
            $job->retries = (int) $job->retries + 1;
            $job->error = mb_substr($e->getMessage(), 0, 1000);
            $job->save();

            return $job->refresh();
        }

        $job->status = PrintJob::STATUS_SUCCESS;
        $job->printed_at = Carbon::now();
        $job->error = null;
        $job->save();

        return $job->refresh();
    }

    /**
     * 失败任务重试；失败次数达到 maxAttempts 时抛 PrintRetryLimitExceededException。
     */
    public function retry(PrintJob $job): PrintJob
    {
        if ($job->isSuccess()) {
            throw new InvalidArgumentException("打印任务 {$job->id} 已打印成功，无需重试；如需补打请使用 reprint()");
        }

        if ((int) $job->retries >= $this->maxAttempts) {
            throw new PrintRetryLimitExceededException(
                "打印任务 {$job->id} 已失败 {$job->retries} 次，达到重试上限 {$this->maxAttempts}"
            );
        }

        return $this->process($job);
    }

    /**
     * 补打：用原任务 payload 生成新的 pending 任务（原任务历史保留），可指定新设备。
     */
    public function reprint(PrintJob $job, ?string $deviceId = null): PrintJob
    {
        $payload = $job->payload;

        if (! is_array($payload) || ! isset($payload['content']) || $payload['content'] === '') {
            throw new InvalidArgumentException("打印任务 {$job->id} 没有可补打的 payload");
        }

        return PrintJob::create([
            'store_id' => $job->store_id,
            'order_number' => $job->order_number,
            'template_id' => $job->template_id,
            'device_id' => $deviceId ?? $job->device_id,
            'type' => $job->type,
            'payload' => $payload,
            'status' => PrintJob::STATUS_PENDING,
            'retries' => 0,
        ])->refresh();
    }

    public function findJob(int $id): PrintJob
    {
        $job = PrintJob::query()->find($id);
        if (! $job) {
            throw new InvalidArgumentException("打印任务 {$id} 不存在");
        }

        return $job;
    }

    public function driver(): PrinterDriver
    {
        return $this->driver;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
