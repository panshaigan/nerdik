<?php

namespace Tests\Feature;

use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityDuplicatePrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_activity_form_prefills_from_duplicate_query_without_hosting_fields(): void
    {
        app()->setLocale('en');

        $user = User::factory()->create();
        $source = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'name' => 'RPG Night',
            'description' => '<p>Hello</p>',
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => null,
            'starts_at' => now()->addDay(),
            'requires_approval' => true,
            'allows_observers' => true,
            'is_host_passive' => true,
            'min_participants' => 2,
            'max_participants' => 5,
            'minimum_age' => 12,
            'duration_in_minutes' => 180,
            'cancellation_deadline_in_hours' => 24,
        ]);
        $tag = Tag::factory()->create();
        $source->tags()->attach($tag->id);

        Livewire::actingAs($user)
            ->withQueryParams(['duplicate' => $source->slug])
            ->test(ManageActivityForm::class)
            ->assertSet('name', 'RPG Night (copy)')
            ->assertSet('description', '<p>Hello</p>')
            ->assertSet('activity_type_id', $source->activity_type_id)
            ->assertSet('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->assertSet('proposal_event_id', null)
            ->assertSet('self_hosted_starts_at', null)
            ->assertSet('place_ids', [])
            ->assertSet('tag_ids', [(int) $tag->id]);
    }

    public function test_manage_activity_form_prefills_proposal_values_and_filters_activity_types_by_slot_intersection(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(8),
        ]);

        $typeA = ActivityType::factory()->create();
        $typeB = ActivityType::factory()->create();
        $typeC = ActivityType::factory()->create();

        $slotOne = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'max_capacity' => 8,
            'starts_at' => now()->addDays(7)->setTime(10, 0),
            'ends_at' => now()->addDays(7)->setTime(11, 30),
        ]);
        $slotOne->activityTypes()->sync([$typeA->id, $typeB->id]);

        $slotTwo = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'max_capacity' => null,
            'starts_at' => now()->addDays(7)->setTime(12, 0),
            'ends_at' => now()->addDays(7)->setTime(14, 30),
        ]);
        $slotTwo->activityTypes()->sync([$typeB->id, $typeC->id]);

        $slotThree = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
            'max_capacity' => 12,
            'starts_at' => now()->addDays(7)->setTime(15, 0),
            'ends_at' => now()->addDays(7)->setTime(16, 0),
        ]);
        $slotThree->activityTypes()->sync([]);

        Livewire::actingAs($user)
            ->withQueryParams([
                'proposal_event_id' => $event->id,
                'proposal_slot_ids' => [$slotOne->id, $slotTwo->id, $slotThree->id],
            ])
            ->test(ManageActivityForm::class)
            ->assertSet('hosting_mode', Activity::HOSTING_MODE_PROPOSED_TO_EVENT)
            ->assertSet('max_participants', 11)
            ->assertSet('duration_in_minutes', 60)
            ->assertViewHas('activityTypes', function ($activityTypes) use ($typeB): bool {
                return $activityTypes->pluck('id')->map(fn ($id) => (int) $id)->values()->all() === [(int) $typeB->id];
            });
    }

    public function test_non_owner_cannot_prefill_activity_from_duplicate_param(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $source = Activity::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'name' => 'Secret Activity',
        ]);

        Livewire::actingAs($stranger)
            ->withQueryParams(['duplicate' => $source->slug])
            ->test(ManageActivityForm::class)
            ->assertSet('name', '')
            ->assertSet('description', '')
            ->assertSet('activity_type_id', null)
            ->assertSet('tag_ids', []);
    }
}
