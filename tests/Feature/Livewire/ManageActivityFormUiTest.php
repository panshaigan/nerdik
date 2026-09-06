<?php

namespace Tests\Feature\Livewire;

use App\Enums\ActivityLogoSource;
use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageActivityFormUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_does_not_render_name_suggestions_popup(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->assertDontSeeHtml('data-activity-name-popup')
            ->assertDontSeeHtml('activity-name-suggestions-popup');
    }

    public function test_edit_form_clamps_legacy_min_participants_below_one(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'min_participants' => 0,
            'max_participants' => 6,
        ]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->assertSet('min_participants', 1)
            ->assertSet('max_participants', 6);
    }

    public function test_save_switches_to_main_details_tab_when_name_missing_from_another_tab(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('tab', 'image')
            ->call('save')
            ->assertSet('tab', 'main-details')
            ->assertHasErrors(['name'])
            ->assertSeeHtml('data-ui="form-errors"');
    }

    public function test_save_switches_to_image_tab_when_tag_logo_selection_missing(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $tag = Tag::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('tab', 'main-details')
            ->set('name', 'Tagged Logo Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('logo_source', ActivityLogoSource::Tag->value)
            ->set('tag_ids', [(int) $tag->id])
            ->call('save')
            ->assertSet('tab', 'image')
            ->assertHasErrors(['selected_tag_media_id']);
    }

    public function test_save_switches_to_first_tab_with_errors_when_multiple_tabs_fail(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $tag = Tag::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('tab', 'image')
            ->set('logo_source', ActivityLogoSource::Tag->value)
            ->set('tag_ids', [(int) $tag->id])
            ->call('save')
            ->assertSet('tab', 'main-details')
            ->assertHasErrors(['name', 'selected_tag_media_id']);
    }

    public function test_save_switches_to_hosting_mode_tab_when_self_hosted_place_missing(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('tab', 'main-details')
            ->set('name', 'Self Hosted Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->call('save')
            ->assertSet('tab', 'hosting-mode')
            ->assertHasErrors(['hosting_mode']);
    }

    public function test_minimum_age_zero_saves_as_null(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        app()->setLocale('en');

        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'No Age Limit Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('minimum_age', 0)
            ->call('save')
            ->assertHasNoErrors();

        $activity = Activity::query()->where('name', 'No Age Limit Activity')->first();
        $this->assertNotNull($activity);
        $this->assertNull($activity->minimum_age);
    }

    public function test_edit_form_loads_duration_and_cancellation_deadline(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'duration_in_minutes' => 180,
            'cancellation_deadline_in_hours' => 24,
        ]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->assertSet('duration_in_minutes', 180)
            ->assertSet('cancellation_deadline_in_hours', 24);
    }
}
