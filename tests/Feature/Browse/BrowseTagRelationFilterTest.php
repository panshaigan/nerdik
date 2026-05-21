<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseEvents;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagRelation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrowseTagRelationFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{parent: Tag, mid: Tag, child: Tag, owner: User, event: Event}
     */
    private function fantasyTagHierarchy(): array
    {
        $owner = User::factory()->create();
        $startsAt = now()->addDays(14)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(5);
        $event = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $category = TagCategory::factory()->create(['key' => 'browse-tag-relation-'.uniqid('', true)]);

        $parent = Tag::factory()->create(['tag_category_id' => $category->id]);
        $mid = Tag::factory()->create(['tag_category_id' => $category->id]);
        $child = Tag::factory()->create(['tag_category_id' => $category->id]);

        TagRelation::query()->create(['tag_id' => $child->id, 'related_tag_id' => $mid->id]);
        TagRelation::query()->create(['tag_id' => $mid->id, 'related_tag_id' => $parent->id]);

        return compact('parent', 'mid', 'child', 'owner', 'event');
    }

    private function scheduledActivityWithTag(
        User $owner,
        Event $event,
        Tag $tag,
        string $name,
    ): Activity {
        $startsAt = now()->addDays(14)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(5);

        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => $name,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $activity->tags()->attach($tag->id);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return $activity;
    }

    public function test_filter_by_mid_tag_includes_child_not_parent_only_listing(): void
    {
        $h = $this->fantasyTagHierarchy();

        $this->scheduledActivityWithTag($h['owner'], $h['event'], $h['child'], 'Browse Tag Child Warhammer');
        $this->scheduledActivityWithTag($h['owner'], $h['event'], $h['mid'], 'Browse Tag Mid Dark Fantasy');
        $this->scheduledActivityWithTag($h['owner'], $h['event'], $h['parent'], 'Browse Tag Parent Fantasy Only');

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class, [
                'only_activities' => true,
                'tag_ids' => [(int) $h['mid']->id],
            ])
            ->assertSee('Browse Tag Child Warhammer')
            ->assertSee('Browse Tag Mid Dark Fantasy')
            ->assertDontSee('Browse Tag Parent Fantasy Only');
    }

    public function test_filter_by_child_tag_matches_only_that_listing(): void
    {
        $h = $this->fantasyTagHierarchy();

        $this->scheduledActivityWithTag($h['owner'], $h['event'], $h['child'], 'Browse Tag Child Warhammer Only');
        $this->scheduledActivityWithTag($h['owner'], $h['event'], $h['mid'], 'Browse Tag Mid Dark Fantasy Other');

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class, [
                'only_activities' => true,
                'tag_ids' => [(int) $h['child']->id],
            ])
            ->assertSee('Browse Tag Child Warhammer Only')
            ->assertDontSee('Browse Tag Mid Dark Fantasy Other');
    }

    public function test_filter_by_parent_tag_includes_all_descendants(): void
    {
        $h = $this->fantasyTagHierarchy();

        $this->scheduledActivityWithTag($h['owner'], $h['event'], $h['child'], 'Browse Tag Child Warhammer');
        $this->scheduledActivityWithTag($h['owner'], $h['event'], $h['mid'], 'Browse Tag Mid Dark Fantasy');
        $this->scheduledActivityWithTag($h['owner'], $h['event'], $h['parent'], 'Browse Tag Parent Fantasy');

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class, [
                'only_activities' => true,
                'tag_ids' => [(int) $h['parent']->id],
            ])
            ->assertSee('Browse Tag Child Warhammer')
            ->assertSee('Browse Tag Mid Dark Fantasy')
            ->assertSee('Browse Tag Parent Fantasy');
    }

    public function test_map_features_respects_tag_descendant_expansion(): void
    {
        $h = $this->fantasyTagHierarchy();

        $startsAt = now()->addDays(14)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(5);

        $place = Place::factory()->venue()->create([
            'latitude' => 51.11,
            'longitude' => 17.03,
        ]);

        $childActivity = Activity::factory()->create([
            'created_by' => $h['owner']->id,
            'updated_by' => $h['owner']->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'name' => 'Map Tag Child Activity Unique',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $childActivity->tags()->attach($h['child']->id);

        $parentOnlyActivity = Activity::factory()->create([
            'created_by' => $h['owner']->id,
            'updated_by' => $h['owner']->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'name' => 'Map Tag Parent Only Activity Unique',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $parentOnlyActivity->tags()->attach($h['parent']->id);

        $res = $this->getJson(route('search.map-features', [
            'min_lat' => 51.0,
            'max_lat' => 51.2,
            'min_lng' => 16.9,
            'max_lng' => 17.2,
            'zoom' => 12,
            'tag_ids' => [(int) $h['mid']->id],
        ]));

        $res->assertOk();
        $names = collect($res->json('features'))
            ->pluck('properties.name')
            ->filter()
            ->values()
            ->all();

        $this->assertContains('Map Tag Child Activity Unique', $names);
        $this->assertNotContains('Map Tag Parent Only Activity Unique', $names);
    }
}
