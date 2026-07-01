<?php

namespace Tests\Feature;

use App\Enums\ParticipationMode;
use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Place;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageActivityFormLotteryTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_hosted_lottery_activity_saves_with_lottery_draw_hours(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $venue = Place::factory()->venue()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;
        $startsAt = now()->addWeek()->format('Y-m-d\TH:i');

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Lottery Self Hosted')
            ->set('activity_type_id', $activityTypeId)
            ->set('participation_mode', ParticipationMode::Lottery->value)
            ->set('lottery_draw_in_hours', 24)
            ->set('cancellation_deadline_in_hours', 12)
            ->set('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->set('self_hosted_place_id', $venue->id)
            ->set('self_hosted_starts_at', $startsAt)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('search.index'));

        $activity = Activity::query()->where('name', 'Lottery Self Hosted')->first();
        $this->assertNotNull($activity);
        $this->assertSame(ParticipationMode::Lottery, $activity->participation_mode);
        $this->assertSame(24, $activity->lottery_draw_in_hours);
        $this->assertSame(12, $activity->cancellation_deadline_in_hours);
        $this->assertSame(Activity::HOSTING_MODE_SELF_HOSTED, (int) $activity->hosting_mode);
        $this->assertNotNull($activity->starts_at);
    }

    public function test_lottery_draw_hours_required_when_lottery_mode_selected(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Missing Draw Time')
            ->set('activity_type_id', $activityTypeId)
            ->set('participation_mode', ParticipationMode::Lottery->value)
            ->set('lottery_draw_in_hours', null)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->call('save')
            ->assertHasErrors(['lottery_draw_in_hours']);
    }

    public function test_cancellation_deadline_below_one_hour_is_normalized_to_null(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Cleared Deadline')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('cancellation_deadline_in_hours', 0)
            ->assertSet('cancellation_deadline_in_hours', null)
            ->call('save')
            ->assertHasNoErrors();

        $activity = Activity::query()->where('name', 'Cleared Deadline')->first();
        $this->assertNotNull($activity);
        $this->assertNull($activity->cancellation_deadline_in_hours);
    }
}
