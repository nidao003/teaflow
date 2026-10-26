<?php

namespace Tests\Feature\Domain\Printing;

use App\Domain\Printing\Contracts\PrinterDriver;
use App\Domain\Printing\Drivers\EscPosPrinterDriver;
use InvalidArgumentException;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Tests\TestCase;

/**
 * ESC/POS 驱动测试：用文件连接器验证真实指令输出，不依赖硬件。
 * mike42/escpos-php 在 PHP 8.4 下有 vendor 级 implicit nullable 弃用提示，故该用例标记 IgnoreDeprecations。
 */
class EscPosPrinterDriverTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    #[IgnoreDeprecations]
    public function test_file_driver_writes_payload(): void
    {
        $path = $this->tempFile();
        $driver = EscPosPrinterDriver::file($path);

        $driver->print("ORDER T20261026001 A012 TOTAL 18.00\n");

        $content = file_get_contents($path);
        $this->assertIsString($content);
        $this->assertStringContainsString('ORDER T20261026001 A012 TOTAL 18.00', $content);
    }

    public function test_empty_payload_rejected(): void
    {
        $driver = EscPosPrinterDriver::file($this->tempFile());

        $this->expectException(InvalidArgumentException::class);

        $driver->print(" \n ");
    }

    #[IgnoreDeprecations]
    public function test_connector_can_be_injected(): void
    {
        $connector = new DummyPrintConnector;
        $driver = EscPosPrinterDriver::fromConnector($connector);

        $driver->print("ORDER A012\n");

        $this->assertInstanceOf(PrinterDriver::class, $driver);
    }

    private function tempFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'teaflow-escpos-');
        $this->assertIsString($path);
        $this->tempFiles[] = $path;

        return $path;
    }
}
