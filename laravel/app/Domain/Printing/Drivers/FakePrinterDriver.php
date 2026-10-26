<?php

namespace App\Domain\Printing\Drivers;

use App\Domain\Printing\Contracts\PrinterDriver;
use RuntimeException;

/**
 * 测试用驱动：记录收到的 payload；可模拟失败，供 PrintService 状态/重试测试使用。
 */
class FakePrinterDriver implements PrinterDriver
{
    /** @var array<int, string> */
    private array $payloads = [];

    private ?string $failure = null;

    public function print(string $payload): void
    {
        if ($this->failure !== null) {
            throw new RuntimeException($this->failure);
        }

        $this->payloads[] = $payload;
    }

    public function failWith(string $message): void
    {
        $this->failure = $message;
    }

    public function succeed(): void
    {
        $this->failure = null;
    }

    /**
     * @return array<int, string>
     */
    public function payloads(): array
    {
        return $this->payloads;
    }

    public function lastPayload(): ?string
    {
        if ($this->payloads === []) {
            return null;
        }

        return $this->payloads[array_key_last($this->payloads)];
    }

    public function count(): int
    {
        return count($this->payloads);
    }

    public function reset(): void
    {
        $this->payloads = [];
        $this->failure = null;
    }
}
