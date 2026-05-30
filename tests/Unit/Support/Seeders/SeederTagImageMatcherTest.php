<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Seeders;

use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Support\Seeders\SeederTagImageMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SeederTagImageMatcherTest extends TestCase
{
    use RefreshDatabase;

    private SeederTagImageMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new SeederTagImageMatcher;
    }

    #[Test]
    public function it_parses_id_and_slug_from_prefixed_filename(): void
    {
        $parsed = $this->matcher->parseBasename('55_high_fantasy.png');

        $this->assertSame(55, $parsed['id']);
        $this->assertSame('high-fantasy', $parsed['slug']);
    }

    #[Test]
    public function it_parses_slug_from_unprefixed_filename(): void
    {
        $parsed = $this->matcher->parseBasename('fantasy.png');

        $this->assertNull($parsed['id']);
        $this->assertSame('fantasy', $parsed['slug']);
    }

    #[Test]
    public function it_resolves_tag_by_id_within_category(): void
    {
        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $tag = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Fantasy',
            'slug' => 'fantasy',
        ]);

        $resolved = $this->matcher->resolve($tag->id, 'fantasy', TagCategory::KEY_GENRE);

        $this->assertNotNull($resolved);
        $this->assertSame($tag->id, $resolved->id);
    }

    #[Test]
    public function it_falls_back_to_slug_when_id_points_at_wrong_tag(): void
    {
        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $otherTag = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $otherTag->id,
            'locale' => 'en',
            'label' => 'Horror',
            'slug' => 'horror',
        ]);

        $cyberpunk = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $cyberpunk->id,
            'locale' => 'en',
            'label' => 'Cyberpunk',
            'slug' => 'cyberpunk',
        ]);

        $resolved = $this->matcher->resolve($otherTag->id, 'cyberpunk', TagCategory::KEY_GENRE);

        $this->assertNotNull($resolved);
        $this->assertSame($cyberpunk->id, $resolved->id);
    }

    #[Test]
    public function it_falls_back_to_slug_when_id_does_not_exist(): void
    {
        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $tag = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Cyberpunk',
            'slug' => 'cyberpunk',
        ]);

        $resolved = $this->matcher->resolve(999, 'cyberpunk', TagCategory::KEY_GENRE);

        $this->assertNotNull($resolved);
        $this->assertSame($tag->id, $resolved->id);
    }
}
