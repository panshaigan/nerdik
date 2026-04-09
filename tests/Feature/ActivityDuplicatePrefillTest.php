<?php

namespace Tests\Feature;

use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
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
}
