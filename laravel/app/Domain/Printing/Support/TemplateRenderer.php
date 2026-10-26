<?php

namespace App\Domain\Printing\Support;

use Illuminate\Support\Arr;
use InvalidArgumentException;
use Stringable;

/**
 * 简单模板渲染：{{var}} / {{ nested.key }} 占位符替换，变量缺失或非标量时抛错。
 */
class TemplateRenderer
{
    private const PATTERN = '/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/';

    /**
     * @param  array<string, mixed>  $vars
     */
    public function render(string $content, array $vars = []): string
    {
        $rendered = preg_replace_callback(self::PATTERN, function (array $matches) use ($vars): string {
            $key = (string) $matches[1];

            if (! Arr::has($vars, $key)) {
                throw new InvalidArgumentException("打印模板变量「{$key}」未提供");
            }

            $value = Arr::get($vars, $key);

            if ($value === null) {
                return '';
            }

            if (! is_scalar($value) && ! $value instanceof Stringable) {
                throw new InvalidArgumentException("打印模板变量「{$key}」不是标量，无法渲染");
            }

            return (string) $value;
        }, $content);

        return $rendered ?? $content;
    }
}
