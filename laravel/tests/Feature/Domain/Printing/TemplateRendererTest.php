<?php

namespace Tests\Feature\Domain\Printing;

use App\Domain\Printing\Support\TemplateRenderer;
use InvalidArgumentException;
use Tests\TestCase;

class TemplateRendererTest extends TestCase
{
    private TemplateRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new TemplateRenderer;
    }

    public function test_render_replaces_nested_and_flat_vars(): void
    {
        $content = "{{store.name}}\n取餐码：{{order.pickup_code}}\n金额：￥{{total_amount}}";

        $rendered = $this->renderer->render($content, [
            'store' => ['name' => '静安店'],
            'order' => ['pickup_code' => 'A012'],
            'total_amount' => '18.00',
        ]);

        $this->assertSame("静安店\n取餐码：A012\n金额：￥18.00", $rendered);
    }

    public function test_render_supports_spacing_and_repeated_placeholders(): void
    {
        $this->assertSame(
            'A012 / A012',
            $this->renderer->render('{{ order.pickup_code }} / {{order.pickup_code}}', [
                'order' => ['pickup_code' => 'A012'],
            ])
        );
    }

    public function test_render_without_placeholder_keeps_content(): void
    {
        $this->assertSame('纯文本小票', $this->renderer->render('纯文本小票'));
    }

    public function test_null_variable_renders_as_empty_string(): void
    {
        $this->assertSame('备注：', $this->renderer->render('备注：{{order.remark}}', [
            'order' => ['remark' => null],
        ]));
    }

    public function test_missing_variable_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->renderer->render('取餐码：{{order.pickup_code}}', ['order' => []]);
    }

    public function test_non_scalar_variable_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->renderer->render('{{order.items}}', [
            'order' => ['items' => [['name' => '珍珠奶茶', 'qty' => 2]]],
        ]);
    }
}
