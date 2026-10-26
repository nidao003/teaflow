<?php

namespace Tests\Feature\Domain\Store;

use App\Domain\Store\Models\Region;
use Database\Seeders\StoreSeeder;

class StoreSeederTest extends StoreTestCase
{
    public function test_seed_creates_region_tree(): void
    {
        $this->seed(StoreSeeder::class);

        $this->assertSame(5, Region::query()->count());
        $this->assertSame(2, Region::query()->whereNull('parent_id')->count());
        $this->assertSame(3, Region::query()->whereNotNull('parent_id')->count());

        $east = Region::query()->where('code', 'RG-EAST')->firstOrFail();
        $this->assertSame(1, $east->level);
        $this->assertSame($east->id.'/', $east->path);

        $shanghai = Region::query()->where('code', 'RG-EAST-SH')->firstOrFail();
        $this->assertSame(2, $shanghai->level);
        $this->assertSame($east->id.'/'.$shanghai->id.'/', $shanghai->path);
        $this->assertSame($east->id, $shanghai->parent_id);

        $this->assertEqualsCanonicalizing(
            ['RG-EAST-SH', 'RG-EAST-ZJ'],
            $east->children()->pluck('code')->all()
        );
    }

    public function test_seed_is_idempotent(): void
    {
        $this->seed(StoreSeeder::class);
        $this->seed(StoreSeeder::class);

        $this->assertSame(5, Region::query()->count());
        $this->assertSame(5, Region::query()->distinct()->count('code'));
    }
}
