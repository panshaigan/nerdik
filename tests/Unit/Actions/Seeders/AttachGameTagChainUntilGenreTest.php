<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Seeders;

use App\Actions\Seeders\AttachGameTagChainUntilGenre;
use App\Models\Activity;
use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AttachGameTagChainUntilGenreTest extends TestCase
{
    use RefreshDatabase;

    private AttachGameTagChainUntilGenre $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AttachGameTagChainUntilGenre;
    }

    #[Test]
    public function it_attaches_all_related_tags_of_the_current_tag_before_stopping_at_genre(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $settingCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_SETTING]);
        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);

        $game = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        $setting = Tag::factory()->create(['tag_category_id' => $settingCategory->id]);
        $urbanFantasy = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);
        $horror = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);
        $beyondGenre = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);

        $game->relatedTags()->attach($setting);
        $setting->relatedTags()->attach([$urbanFantasy->id, $horror->id]);
        $urbanFantasy->relatedTags()->attach($beyondGenre);

        $activity = Activity::factory()->create();

        ($this->action)($activity, $game);

        $attachedIds = $activity->tags()->pluck('tags.id')->all();

        $this->assertEqualsCanonicalizing(
            [$game->id, $setting->id, $urbanFantasy->id, $horror->id],
            $attachedIds,
        );
        $this->assertNotContains($beyondGenre->id, $attachedIds);
    }

    #[Test]
    public function it_attaches_all_related_tags_when_the_current_tag_is_genre(): void
    {
        $genreCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GENRE]);
        $settingCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_SETTING]);

        $genre = Tag::factory()->create(['tag_category_id' => $genreCategory->id]);
        $setting = Tag::factory()->create(['tag_category_id' => $settingCategory->id]);
        $deeper = Tag::factory()->create(['tag_category_id' => $settingCategory->id]);

        $genre->relatedTags()->attach($setting);
        $setting->relatedTags()->attach($deeper);

        $activity = Activity::factory()->create();

        ($this->action)($activity, $genre);

        $attachedIds = $activity->tags()->pluck('tags.id')->all();

        $this->assertEqualsCanonicalizing([$genre->id, $setting->id], $attachedIds);
        $this->assertNotContains($deeper->id, $attachedIds);
    }

    #[Test]
    public function it_attaches_all_reachable_related_tags_when_no_genre_exists(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $mechanicCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_MECHANIC]);

        $game = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        $mechanic = Tag::factory()->create(['tag_category_id' => $mechanicCategory->id]);

        $game->relatedTags()->attach($mechanic);

        $activity = Activity::factory()->create();

        ($this->action)($activity, $game);

        $this->assertEqualsCanonicalizing(
            [$game->id, $mechanic->id],
            $activity->tags()->pluck('tags.id')->all(),
        );
    }

    #[Test]
    public function it_does_not_revisit_tags_in_circular_relations(): void
    {
        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $settingCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_SETTING]);

        $game = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        $setting = Tag::factory()->create(['tag_category_id' => $settingCategory->id]);

        $game->relatedTags()->attach($setting);
        $setting->relatedTags()->attach($game);

        $activity = Activity::factory()->create();

        ($this->action)($activity, $game);

        $this->assertEqualsCanonicalizing([$game->id, $setting->id], $activity->tags()->pluck('tags.id')->all());
    }
}
