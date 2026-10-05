<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Welcome;

use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Support\Welcome\WelcomeHeroTagImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AttachesFixtureMedia;
use Tests\TestCase;

final class WelcomeHeroTagImageResolverTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        WelcomeHeroTagImageResolver::forgetCachedHeroImages();
    }

    #[Test]
    public function test_second_resolve_uses_cache_without_requerying_media(): void
    {
        config(['cache.default' => 'array']);

        $category = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $tag = Tag::factory()->create(['tag_category_id' => $category->id]);
        TagTranslation::factory()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Cached Hero Tag',
        ]);
        $this->attachTagSampleMedia($tag);

        $resolver = app(WelcomeHeroTagImageResolver::class);
        $cacheKey = WelcomeHeroTagImageResolver::cacheKeyForLocale('en');

        $first = $resolver->resolve();
        $this->assertNotNull($first);
        $this->assertTrue(Cache::has($cacheKey));

        DB::enableQueryLog();
        $second = $resolver->resolve();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertNotNull($second);
        $this->assertSame('Cached Hero Tag', $second->label);
        $this->assertCount(0, $queries);
    }

    #[Test]
    public function test_empty_hero_state_is_cached(): void
    {
        config(['cache.default' => 'array']);

        $resolver = app(WelcomeHeroTagImageResolver::class);
        $cacheKey = WelcomeHeroTagImageResolver::cacheKeyForLocale('en');

        $this->assertNull($resolver->resolve());
        $this->assertTrue(Cache::has($cacheKey));

        DB::enableQueryLog();
        $this->assertNull($resolver->resolve());
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(0, $queries);
    }

    #[Test]
    public function test_soft_deleted_tag_media_is_not_chosen_for_hero(): void
    {
        config(['cache.default' => 'array']);

        $deletedCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $deletedTag = Tag::factory()->create(['tag_category_id' => $deletedCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $deletedTag->id,
            'locale' => 'en',
            'label' => 'Deleted Tag',
        ]);
        $this->attachTagSampleMedia($deletedTag);
        $deletedTag->delete();

        $activeCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $activeTag = Tag::factory()->create(['tag_category_id' => $activeCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $activeTag->id,
            'locale' => 'en',
            'label' => 'Active Hero Tag',
        ]);
        $this->attachTagSampleMedia($activeTag);

        $hero = app(WelcomeHeroTagImageResolver::class)->resolve();

        $this->assertNotNull($hero);
        $this->assertSame('Active Hero Tag', $hero->label);
    }
}
