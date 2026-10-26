<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Marketing;

use App\Domain\Marketing\Exceptions\MarketingRuleException;
use App\Domain\Marketing\Models\MemberTag;
use App\Domain\Marketing\Models\MemberTagRelation;
use App\Domain\Marketing\Services\TagService;
use InvalidArgumentException;

class TagServiceTest extends MarketingTestCase
{
    private TagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(TagService::class);
    }

    public function test_attach_and_query_tags(): void
    {
        $newCustomer = $this->service->createTag(['name' => '新客', 'color' => '#67C23A']);
        $valuable = $this->service->createTag(['name' => '高价值']);

        $first = $this->createMember();
        $second = $this->createMember();

        $this->service->attach($first, (int) $newCustomer->getKey());
        $this->service->attach($second, (int) $newCustomer->getKey());
        $this->service->attach($first, (int) $valuable->getKey());

        $this->assertCount(2, $this->service->tagsOfMember($first));
        $this->assertCount(1, $this->service->tagsOfMember($second));
        $this->assertSame([$first, $second], $this->service->memberIdsByTag((int) $newCustomer->getKey()));
        $this->assertSame([$first], $this->service->memberIdsByTagName('高价值'));
        $this->assertSame('#67C23A', $newCustomer->color);
        $this->assertSame([], $this->service->memberIdsByTagName('不存在的标签'));
    }

    public function test_attach_is_idempotent(): void
    {
        $tag = $this->service->createTag(['name' => '新客']);
        $member = $this->createMember();

        $this->service->attach($member, (int) $tag->getKey());
        $this->service->attach($member, (int) $tag->getKey());

        $this->assertSame(1, MemberTagRelation::query()->count());
    }

    public function test_detach_and_sync_tags(): void
    {
        $first = $this->service->createTag(['name' => '新客']);
        $second = $this->service->createTag(['name' => '高价值']);
        $member = $this->createMember();

        $this->service->attach($member, (int) $first->getKey());
        $this->service->attach($member, (int) $second->getKey());

        $synced = $this->service->syncTags($member, [(int) $second->getKey()]);
        $this->assertSame([(int) $second->getKey()], $synced);
        $this->assertSame(['高价值'], $this->service->tagsOfMember($member)->pluck('name')->all());

        $this->assertTrue($this->service->detach($member, (int) $second->getKey()));
        $this->assertCount(0, $this->service->tagsOfMember($member));
    }

    public function test_duplicate_tag_and_missing_member_are_rejected(): void
    {
        $this->service->createTag(['name' => '新客']);

        try {
            $this->service->createTag(['name' => '新客']);
            $this->fail('重复标签名应当被拒绝');
        } catch (MarketingRuleException) {
            $this->assertSame(1, MemberTag::query()->count());
        }

        $tag = $this->service->findOrCreateTag('高价值');
        $this->expectException(InvalidArgumentException::class);
        $this->service->attach(9999, (int) $tag->getKey());
    }
}
