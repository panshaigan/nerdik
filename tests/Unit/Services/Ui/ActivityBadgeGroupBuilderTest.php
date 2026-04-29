<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ui;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Domain\ActivityBadges\ActivityBadgeGroupConfig;
use App\Domain\ActivityBadges\ActivityBadgeItem;
use App\Domain\ActivityBadges\ActivityBadgeKind;
use App\Domain\ActivityBadges\ActivityBadgePreset;
use App\Enums\BadgeSemantic;
use App\Models\Activity;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityBadgeGroupBuilderTest extends TestCase
{
    use RefreshDatabase;

    private ActivityBadgeGroupBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ActivityBadgeGroupBuilder;
    }

    #[Test]
    public function activity_hero_orders_type_tags_then_age_and_hides_meta_not_in_surface(): void
    {
        $game = TagCategory::factory()->create(['key' => 'game']);
        $genre = TagCategory::factory()->create(['key' => 'genre']);
        $tagA = Tag::factory()->create(['tag_category_id' => $game->id]);
        TagTranslation::factory()->create(['tag_id' => $tagA->id, 'locale' => 'en', 'label' => 'Alpha']);
        $tagB = Tag::factory()->create(['tag_category_id' => $genre->id]);
        TagTranslation::factory()->create(['tag_id' => $tagB->id, 'locale' => 'en', 'label' => 'Beta']);

        $activity = Activity::factory()->create([
            'requires_approval' => true,
            'allows_observers' => false,
            'minimum_age' => 16,
        ]);
        $activity->tags()->attach([$tagA->id, $tagB->id]);
        $activity->load(['tags.translations', 'tags.tagCategory', 'activityType']);

        $items = $this->builder->build($activity, ActivityBadgeGroupConfig::activityHero());

        $this->assertCount(4, $items);
        $this->assertSame(ActivityBadgeKind::MinimumAge, $items[0]->kind);
        $this->assertSame('16+', $items[0]->label);
        $this->assertSame(ActivityBadgeKind::TaxonomyTag, $items[1]->kind);
        $this->assertSame(ActivityBadgeKind::TaxonomyTag, $items[2]->kind);
        $this->assertSame(ActivityBadgeKind::RequiresApproval, $items[3]->kind);
        $gameItem = collect($items)->first(fn (ActivityBadgeItem $i) => $i->label === 'Alpha');
        $genreItem = collect($items)->first(fn (ActivityBadgeItem $i) => $i->label === 'Beta');
        $this->assertNotNull($gameItem);
        $this->assertNotNull($genreItem);
        $this->assertSame(BadgeSemantic::Neutral, $gameItem->semantic);
        $this->assertSame(BadgeSemantic::Neutral, $genreItem->semantic);
    }

    #[Test]
    public function event_slot_orders_tags_then_meta_without_activity_type(): void
    {
        $game = TagCategory::factory()->create(['key' => 'game']);
        $topic = TagCategory::factory()->create(['key' => 'topic']);
        $tagGame = Tag::factory()->create(['tag_category_id' => $game->id]);
        TagTranslation::factory()->create(['tag_id' => $tagGame->id, 'locale' => 'en', 'label' => 'G']);
        $tagTopic = Tag::factory()->create(['tag_category_id' => $topic->id]);
        TagTranslation::factory()->create(['tag_id' => $tagTopic->id, 'locale' => 'en', 'label' => 'T']);

        $activity = Activity::factory()->create(['minimum_age' => 12, 'activity_type_id' => null]);
        $activity->tags()->attach([$tagGame->id, $tagTopic->id]);
        $activity->load(['tags.translations', 'tags.tagCategory', 'activityType']);

        $items = $this->builder->build($activity, ActivityBadgeGroupConfig::eventSlotCard());

        $kinds = array_map(fn (ActivityBadgeItem $i) => $i->kind, $items);
        $this->assertSame([ActivityBadgeKind::MinimumAge, ActivityBadgeKind::TaxonomyTag, ActivityBadgeKind::TaxonomyTag], $kinds);
        $this->assertSame('12+', $items[0]->label);
        $this->assertSame('G', $items[1]->label);
        $this->assertSame('T', $items[2]->label);
    }

    #[Test]
    public function event_slot_only_tag_category_override_via_dto(): void
    {
        $game = TagCategory::factory()->create(['key' => 'game']);
        $topic = TagCategory::factory()->create(['key' => 'topic']);
        $tagGame = Tag::factory()->create(['tag_category_id' => $game->id]);
        TagTranslation::factory()->create(['tag_id' => $tagGame->id, 'locale' => 'en', 'label' => 'G']);
        $tagTopic = Tag::factory()->create(['tag_category_id' => $topic->id]);
        TagTranslation::factory()->create(['tag_id' => $tagTopic->id, 'locale' => 'en', 'label' => 'T']);

        $activity = Activity::factory()->create(['minimum_age' => 12, 'activity_type_id' => null]);
        $activity->tags()->attach([$tagGame->id, $tagTopic->id]);
        $activity->load(['tags.translations', 'tags.tagCategory', 'activityType']);

        $config = new ActivityBadgeGroupConfig(
            ActivityBadgePreset::EventSlotCard,
            ['game'],
            null,
            [],
            [],
        );
        $items = $this->builder->build($activity, $config);

        $this->assertCount(2, $items);
        $this->assertSame(ActivityBadgeKind::MinimumAge, $items[0]->kind);
        $this->assertSame(ActivityBadgeKind::TaxonomyTag, $items[1]->kind);
        $this->assertSame('G', $items[1]->label);
    }

    #[Test]
    public function browse_card_shows_type_allowed_tags_and_minimum_age(): void
    {
        $activity = Activity::factory()->create([
            'requires_approval' => true,
            'minimum_age' => 18,
        ]);
        $game = TagCategory::factory()->create(['key' => 'game']);
        $tag = Tag::factory()->create(['tag_category_id' => $game->id]);
        TagTranslation::factory()->create(['tag_id' => $tag->id, 'locale' => 'en', 'label' => 'Solo']);
        $activity->tags()->attach([$tag->id]);
        $activity->load(['tags.translations', 'tags.tagCategory', 'activityType']);

        $items = $this->builder->build($activity, ActivityBadgeGroupConfig::browseCard());

        $this->assertCount(2, $items);
        $this->assertSame(ActivityBadgeKind::MinimumAge, $items[0]->kind);
        $this->assertSame('18+', $items[0]->label);
        $this->assertSame(ActivityBadgeKind::TaxonomyTag, $items[1]->kind);
        $this->assertSame('Solo', $items[1]->label);
    }

    #[Test]
    public function semantic_override_is_applied(): void
    {
        $activity = Activity::factory()->create(['minimum_age' => 10]);
        $activity->load(['tags.translations', 'tags.tagCategory']);

        $config = new ActivityBadgeGroupConfig(
            ActivityBadgePreset::ActivityHero,
            null,
            null,
            [ActivityBadgeKind::MinimumAge->value => BadgeSemantic::Warning->value],
            [],
        );

        $items = $this->builder->build($activity, $config);
        $ageItem = collect($items)->first(fn (ActivityBadgeItem $i) => $i->kind === ActivityBadgeKind::MinimumAge);
        $this->assertNotNull($ageItem);
        $this->assertSame(BadgeSemantic::Warning, $ageItem->semantic);
    }

    #[Test]
    public function build_activity_type_chips_skips_empty_labels(): void
    {
        $items = $this->builder->buildActivityTypeChips([' ', 'RPG', '']);
        $this->assertCount(1, $items);
        $this->assertSame('RPG', $items[0]->label);
        $this->assertSame(ActivityBadgeKind::ActivityType, $items[0]->kind);
    }

    #[Test]
    public function taxonomy_semantic_uses_config_file_per_category(): void
    {
        Config::set('activity-badges.semantic_by_tag_category.game', 'warning');

        $game = TagCategory::factory()->create(['key' => 'game']);
        $tag = Tag::factory()->create(['tag_category_id' => $game->id]);
        TagTranslation::factory()->create(['tag_id' => $tag->id, 'locale' => 'en', 'label' => 'Zed']);
        $activity = Activity::factory()->create(['minimum_age' => null, 'requires_approval' => false, 'allows_observers' => false]);
        $activity->tags()->attach([$tag->id]);
        $activity->load(['tags.translations', 'tags.tagCategory', 'activityType']);

        $items = $this->builder->build($activity, ActivityBadgeGroupConfig::activityHero());

        $tagItem = collect($items)->first(fn (ActivityBadgeItem $i) => $i->kind === ActivityBadgeKind::TaxonomyTag);
        $this->assertNotNull($tagItem);
        $this->assertSame(BadgeSemantic::Warning, $tagItem->semantic);
    }

    #[Test]
    public function taxonomy_kind_override_forces_same_semantic_for_all_tag_categories(): void
    {
        $game = TagCategory::factory()->create(['key' => 'game']);
        $genre = TagCategory::factory()->create(['key' => 'genre']);
        $tagGame = Tag::factory()->create(['tag_category_id' => $game->id]);
        TagTranslation::factory()->create(['tag_id' => $tagGame->id, 'locale' => 'en', 'label' => 'G']);
        $tagGenre = Tag::factory()->create(['tag_category_id' => $genre->id]);
        TagTranslation::factory()->create(['tag_id' => $tagGenre->id, 'locale' => 'en', 'label' => 'R']);
        $activity = Activity::factory()->create(['minimum_age' => null, 'requires_approval' => false, 'allows_observers' => false]);
        $activity->tags()->attach([$tagGame->id, $tagGenre->id]);
        $activity->load(['tags.translations', 'tags.tagCategory', 'activityType']);

        $config = ActivityBadgeGroupConfig::activityHero([
            ActivityBadgeKind::TaxonomyTag->value => BadgeSemantic::Warning->value,
        ]);
        $items = $this->builder->build($activity, $config);

        $taxonomy = array_values(array_filter($items, fn (ActivityBadgeItem $i) => $i->kind === ActivityBadgeKind::TaxonomyTag));
        $this->assertCount(2, $taxonomy);
        $this->assertSame(BadgeSemantic::Warning, $taxonomy[0]->semantic);
        $this->assertSame(BadgeSemantic::Warning, $taxonomy[1]->semantic);
    }

    #[Test]
    public function taxonomy_semantic_dto_override_per_category_wins_over_config(): void
    {
        Config::set('activity-badges.semantic_by_tag_category.game', 'info');

        $game = TagCategory::factory()->create(['key' => 'game']);
        $tag = Tag::factory()->create(['tag_category_id' => $game->id]);
        TagTranslation::factory()->create(['tag_id' => $tag->id, 'locale' => 'en', 'label' => 'X']);
        $activity = Activity::factory()->create(['minimum_age' => null, 'requires_approval' => false, 'allows_observers' => false]);
        $activity->tags()->attach([$tag->id]);
        $activity->load(['tags.translations', 'tags.tagCategory', 'activityType']);

        $config = ActivityBadgeGroupConfig::activityHero([], ['game' => BadgeSemantic::Error->value]);
        $items = $this->builder->build($activity, $config);

        $tagItem = collect($items)->first(fn (ActivityBadgeItem $i) => $i->label === 'X');
        $this->assertNotNull($tagItem);
        $this->assertSame(BadgeSemantic::Error, $tagItem->semantic);
    }

    #[Test]
    public function taxonomy_icon_uses_config_per_category(): void
    {
        Config::set('activity-badges.icon_by_tag_category.game', 'o-bolt');

        $game = TagCategory::factory()->create(['key' => 'game']);
        $tag = Tag::factory()->create(['tag_category_id' => $game->id]);
        TagTranslation::factory()->create(['tag_id' => $tag->id, 'locale' => 'en', 'label' => 'Iconic']);
        $activity = Activity::factory()->create(['minimum_age' => null, 'requires_approval' => false, 'allows_observers' => false]);
        $activity->tags()->attach([$tag->id]);
        $activity->load(['tags.translations', 'tags.tagCategory', 'activityType']);

        $items = $this->builder->build($activity, ActivityBadgeGroupConfig::activityHero());
        $tagItem = collect($items)->first(fn (ActivityBadgeItem $i) => $i->label === 'Iconic');

        $this->assertNotNull($tagItem);
        $this->assertSame('o-bolt', $tagItem->icon);
    }

    #[Test]
    public function taxonomy_icon_dto_override_per_category_wins_over_config(): void
    {
        Config::set('activity-badges.icon_by_tag_category.game', 'o-book-open');

        $game = TagCategory::factory()->create(['key' => 'game']);
        $tag = Tag::factory()->create(['tag_category_id' => $game->id]);
        TagTranslation::factory()->create(['tag_id' => $tag->id, 'locale' => 'en', 'label' => 'Override']);
        $activity = Activity::factory()->create(['minimum_age' => null, 'requires_approval' => false, 'allows_observers' => false]);
        $activity->tags()->attach([$tag->id]);
        $activity->load(['tags.translations', 'tags.tagCategory', 'activityType']);

        $config = ActivityBadgeGroupConfig::activityHero();
        $config = new ActivityBadgeGroupConfig(
            $config->preset,
            $config->onlyTagCategoryKeys,
            $config->tagsOverride,
            $config->semanticByKindValue,
            $config->semanticByTagCategoryKeyValue,
            $config->iconByKind,
            ['game' => 'o-fire'],
        );

        $items = $this->builder->build($activity, $config);
        $tagItem = collect($items)->first(fn (ActivityBadgeItem $i) => $i->label === 'Override');

        $this->assertNotNull($tagItem);
        $this->assertSame('o-fire', $tagItem->icon);
    }

    #[Test]
    public function tag_label_falls_back_when_missing_translation(): void
    {
        $game = TagCategory::factory()->create(['key' => 'game']);
        $tag = Tag::factory()->create(['tag_category_id' => $game->id]);
        $tag->load('translations');

        $label = $this->builder->tagLabel($tag, 'en');
        $this->assertSame('#'.$tag->id, $label);
    }
}
