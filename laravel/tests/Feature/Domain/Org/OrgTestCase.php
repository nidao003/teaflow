<?php

namespace Tests\Feature\Domain\Org;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 组织/权限域测试基类。
 *
 * 测试库策略：本机尚未配置测试 MySQL（phpunit.xml 里 DB_CONNECTION 被注释、无 .env），
 * 本域测试固定使用 SQLite 内存库 + RefreshDatabase，做到不依赖外部数据库、不污染任何库。
 * 这里只改运行时 config，不改 phpunit.xml / config/*.php 文件。
 */
abstract class OrgTestCase extends TestCase
{
    use RefreshDatabase;

    protected function usingInMemoryDatabase(): bool
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);

        return true;
    }
}
