<?php

namespace Tests\Feature\Domain\Printing;

use App\Domain\Printing\Models\PrintTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 打印域测试基座：RefreshDatabase（phpunit.xml 已固定 sqlite :memory:）。
 */
abstract class PrintingTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createTemplate(array $overrides = []): PrintTemplate
    {
        return PrintTemplate::create(array_merge([
            'store_id' => null,
            'type' => PrintTemplate::TYPE_RECEIPT,
            'name' => '测试小票模板',
            'content' => "{{store.name}}\n订单号：{{order.number}}\n取餐码：{{order.pickup_code}}\n金额：￥{{total_amount}}",
            'width' => 58,
            'status' => PrintTemplate::STATUS_ACTIVE,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    protected function templateVars(): array
    {
        return [
            'store' => ['name' => '静安店', 'phone' => '021-12345678'],
            'order' => ['number' => 'T20261026001', 'pickup_code' => 'A012'],
            'total_amount' => '18.00',
        ];
    }
}
