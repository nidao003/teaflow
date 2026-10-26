<?php

namespace Database\Seeders;

use App\Domain\Printing\Models\PrintTemplate;
use Illuminate\Database\Seeder;

/**
 * 打印域演示模板：小票 / 标签 / 出品单。按 (store_id=null, type, name) upsert，可重复执行。
 */
class PrintingSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'type' => PrintTemplate::TYPE_RECEIPT,
                'name' => '门店小票（58mm）',
                'width' => 58,
                'content' => <<<'TPL'
{{store.name}}
{{store.address}}

订单号：{{order.number}}
取餐码：{{order.pickup_code}}
下单时间：{{order.created_at}}

------------------------------
{{order.items}}
------------------------------
合计：￥{{total_amount}}
支付方式：{{pay_method}}

谢谢惠顾，欢迎再次光临！
TPL,
            ],
            [
                'type' => PrintTemplate::TYPE_PRODUCTION,
                'name' => '出品单（58mm）',
                'width' => 58,
                'content' => <<<'TPL'
【出品单】{{store.name}}
订单号：{{order.number}}
取餐码：{{order.pickup_code}}
------------------------------
{{order.items}}
------------------------------
备注：{{order.remark}}
TPL,
            ],
            [
                'type' => PrintTemplate::TYPE_LABEL,
                'name' => '杯贴标签（40mm）',
                'width' => 40,
                'content' => <<<'TPL'
{{store.name}}
{{order.pickup_code}}  {{order.number}}
{{order.item_name}} / {{order.spec}}
TPL,
            ],
        ];

        foreach ($templates as $template) {
            PrintTemplate::query()->updateOrCreate(
                [
                    'store_id' => null,
                    'type' => $template['type'],
                    'name' => $template['name'],
                ],
                [
                    'content' => $template['content'],
                    'width' => $template['width'],
                    'status' => PrintTemplate::STATUS_ACTIVE,
                ]
            );
        }
    }
}
