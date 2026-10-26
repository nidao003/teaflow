<?php

namespace App\Domain\Printing\Drivers;

use App\Domain\Printing\Contracts\PrinterDriver;
use App\Domain\Printing\Exceptions\PrintDriverException;
use Closure;
use InvalidArgumentException;
use Mike42\Escpos\CapabilityProfile;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\PrintConnector;
use Mike42\Escpos\Printer;
use Throwable;

/**
 * ESC/POS 驱动：基于 mike42/escpos-php，支持文件/网络连接，connector 与 profile 可注入。
 *
 * - EscPosPrinterDriver::file('/tmp/receipt.bin')：写入文件（调试/测试/中转）
 * - EscPosPrinterDriver::network('192.168.1.50', 9100)：直连网络打印机
 * - 连接器延迟到 print() 时才建立，避免只构造驱动就占用设备；未注入连接器时退化为 DummyPrintConnector
 */
class EscPosPrinterDriver implements PrinterDriver
{
    private readonly ?PrintConnector $connector;

    /** @var (Closure(): PrintConnector)|null */
    private readonly ?Closure $connectorFactory;

    /**
     * @param  PrintConnector|(Closure(): PrintConnector)|null  $connector
     */
    public function __construct(
        PrintConnector|Closure|null $connector = null,
        private readonly ?CapabilityProfile $profile = null,
        private readonly bool $autoCut = true,
    ) {
        if ($connector instanceof Closure) {
            $this->connector = null;
            $this->connectorFactory = $connector;
        } else {
            $this->connector = $connector;
            $this->connectorFactory = null;
        }
    }

    public static function file(string $path, bool $autoCut = true): self
    {
        return new self(static fn (): PrintConnector => new FilePrintConnector($path), null, $autoCut);
    }

    public static function network(string $host, int $port = 9100, int $timeout = 3, bool $autoCut = true): self
    {
        return new self(
            static fn (): PrintConnector => new NetworkPrintConnector($host, $port, $timeout),
            null,
            $autoCut
        );
    }

    public static function fromConnector(PrintConnector $connector, bool $autoCut = true): self
    {
        return new self($connector, null, $autoCut);
    }

    public function print(string $payload): void
    {
        if (trim($payload) === '') {
            throw new InvalidArgumentException('打印内容不能为空');
        }

        $connector = $this->resolveConnector();
        $printer = null;

        try {
            $printer = new Printer($connector, $this->profile);
            $printer->text($payload);

            if ($this->autoCut) {
                $printer->cut();
            }
        } catch (Throwable $e) {
            throw new PrintDriverException('ESC/POS 打印失败：'.$e->getMessage(), 0, $e);
        } finally {
            if ($printer !== null) {
                $printer->close();
            } else {
                $connector->finalize();
            }
        }
    }

    private function resolveConnector(): PrintConnector
    {
        if ($this->connector !== null) {
            return $this->connector;
        }

        if ($this->connectorFactory !== null) {
            return ($this->connectorFactory)();
        }

        return new DummyPrintConnector;
    }
}
