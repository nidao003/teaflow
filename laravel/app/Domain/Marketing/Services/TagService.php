<?php

declare(strict_types=1);

namespace App\Domain\Marketing\Services;

use App\Domain\Marketing\Enums\Status;
use App\Domain\Marketing\Exceptions\MarketingRuleException;
use App\Domain\Marketing\Models\MemberTag;
use App\Domain\Marketing\Models\MemberTagRelation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 会员标签服务：建标签、打标/去标/同步标签、按标签反查会员。
 */
class TagService
{
    public const DEFAULT_COLOR = '#409EFF';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTag(array $attributes): MemberTag
    {
        $name = $this->requireString($attributes['name'] ?? null, '标签名称', 64);
        $color = $this->optionalString($attributes['color'] ?? null, '标签颜色', 16) ?? self::DEFAULT_COLOR;

        if (MemberTag::query()->where('name', $name)->exists()) {
            throw MarketingRuleException::duplicateTag($name);
        }

        $status = $attributes['status'] ?? Status::ACTIVE;
        if (! is_int($status) && ! (is_string($status) && ctype_digit($status))) {
            throw new InvalidArgumentException('非法标签状态');
        }
        $status = (int) $status;
        Status::assertValid($status);

        return MemberTag::query()->create([
            'name' => $name,
            'color' => $color,
            'status' => $status,
        ]);
    }

    public function findOrCreateTag(string $name, ?string $color = null): MemberTag
    {
        $name = $this->requireString($name, '标签名称', 64);
        $existing = MemberTag::query()->where('name', $name)->first();
        if ($existing !== null) {
            return $existing;
        }

        return $this->createTag(['name' => $name, 'color' => $color]);
    }

    public function find(int $tagId): MemberTag
    {
        return MemberTag::query()->findOrFail($tagId);
    }

    /**
     * 打标（幂等）。
     */
    public function attach(int $memberId, int $tagId): MemberTagRelation
    {
        $this->assertPositiveInt($memberId, 'member_id');
        $this->find($tagId);
        $this->assertMemberExists($memberId);

        return MemberTagRelation::query()->firstOrCreate([
            'member_id' => $memberId,
            'tag_id' => $tagId,
        ]);
    }

    /**
     * 去标，返回是否实际删除。
     */
    public function detach(int $memberId, int $tagId): bool
    {
        $this->assertPositiveInt($memberId, 'member_id');

        return MemberTagRelation::query()
            ->where('member_id', $memberId)
            ->where('tag_id', $tagId)
            ->delete() > 0;
    }

    /**
     * 覆盖式同步会员标签集合，返回最终标签 id 列表。
     *
     * @param  list<int|string>  $tagIds
     * @return list<int>
     */
    public function syncTags(int $memberId, array $tagIds): array
    {
        $this->assertPositiveInt($memberId, 'member_id');
        $this->assertMemberExists($memberId);

        $normalized = [];
        foreach ($tagIds as $tagId) {
            $normalized[] = $this->requirePositiveInt($tagId, 'tag_id');
        }
        $normalized = array_values(array_unique($normalized));

        foreach ($normalized as $tagId) {
            $this->find($tagId);
        }

        MemberTagRelation::query()
            ->where('member_id', $memberId)
            ->whereNotIn('tag_id', $normalized)
            ->delete();

        foreach ($normalized as $tagId) {
            MemberTagRelation::query()->firstOrCreate([
                'member_id' => $memberId,
                'tag_id' => $tagId,
            ]);
        }

        return $normalized;
    }

    /**
     * @return Collection<int, MemberTag>
     */
    public function tagsOfMember(int $memberId): Collection
    {
        $tagIds = MemberTagRelation::query()
            ->where('member_id', $memberId)
            ->pluck('tag_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($tagIds === []) {
            return new Collection;
        }

        return MemberTag::query()->whereIn('id', $tagIds)->orderBy('id')->get();
    }

    /**
     * @return list<int>
     */
    public function memberIdsByTag(int $tagId): array
    {
        return MemberTagRelation::query()
            ->where('tag_id', $tagId)
            ->orderBy('member_id')
            ->pluck('member_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function memberIdsByTagName(string $name): array
    {
        $tag = MemberTag::query()->where('name', trim($name))->first();
        if ($tag === null) {
            return [];
        }

        return $this->memberIdsByTag((int) $tag->getKey());
    }

    protected function assertMemberExists(int $memberId): void
    {
        if (! DB::table('members')->where('id', $memberId)->exists()) {
            throw new InvalidArgumentException('会员不存在：'.$memberId);
        }
    }

    protected function assertPositiveInt(int $value, string $field): void
    {
        if ($value <= 0) {
            throw new InvalidArgumentException($field.' 必须为正整数');
        }
    }

    protected function requirePositiveInt(mixed $value, string $field): int
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw new InvalidArgumentException($field.' 必须为正整数');
        }

        $int = (int) $value;
        if ($int <= 0) {
            throw new InvalidArgumentException($field.' 必须为正整数');
        }

        return $int;
    }

    protected function requireString(mixed $value, string $label, int $maxLength): string
    {
        if (! is_string($value)) {
            throw new InvalidArgumentException($label.'不能为空');
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException($label.'不能为空');
        }
        if (mb_strlen($trimmed) > $maxLength) {
            throw new InvalidArgumentException($label.'长度不能超过 '.$maxLength.' 个字符');
        }

        return $trimmed;
    }

    protected function optionalString(mixed $value, string $label, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->requireString($value, $label, $maxLength);
    }
}
