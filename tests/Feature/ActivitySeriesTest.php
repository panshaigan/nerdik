<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Activities\ManageActivityForm;
use App\Livewire\Activities\ShowActivitySeries;
use App\Models\Activity;
use App\Models\ActivitySeries;
use App\Models\ActivityType;
use App\Models\User;
use App\Support\Events\EventEditionDateBumper;
use App\Support\Ui\BrowseListingCardPresenter;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivitySeriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createSelfHostedActivity(array $attributes): Activity
    {
        $activity = Activity::factory()->selfHosted()->create($attributes);

        $overrides = array_filter([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => $attributes['starts_at'] ?? null,
            'ends_at' => $attributes['ends_at'] ?? null,
            'cancelled_at' => $attributes['cancelled_at'] ?? null,
            'activity_series_id' => $attributes['activity_series_id'] ?? null,
            'name' => $attributes['name'] ?? null,
            'created_by' => $attributes['created_by'] ?? null,
            'updated_by' => $attributes['updated_by'] ?? null,
        ], fn ($value) => $value !== null);

        if ($overrides !== []) {
            $activity->update($overrides);
        }

        return $activity->refresh();
    }

    public function test_duplicate_prefills_series_and_bumps_self_hosted_dates_from_latest_session(): void
    {
        app()->setLocale('en');
        $this->seed(ActivityTypeSeeder::class);

        $user = User::factory()->create();
        $series = ActivitySeries::factory()->create([
            'name' => 'Weekly RPG',
            'created_by' => $user->id,
        ]);

        $olderStarts = now()->utc()->addMonths(1)->setTime(18, 30, 0);
        $latestStarts = now()->utc()->addMonths(2)->setTime(19, 15, 0);

        $older = $this->createSelfHostedActivity([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'activity_series_id' => $series->id,
            'name' => 'Weekly RPG II',
            'starts_at' => $olderStarts,
            'ends_at' => (clone $olderStarts)->addHours(4),
        ]);
        $this->createSelfHostedActivity([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'activity_series_id' => $series->id,
            'name' => 'Weekly RPG III',
            'starts_at' => $latestStarts,
            'ends_at' => (clone $latestStarts)->addHours(4),
        ]);

        $dateBumper = app(EventEditionDateBumper::class);
        $expectedStarts = $dateBumper->formatForDatetimeLocal($latestStarts);

        Livewire::actingAs($user)
            ->withQueryParams(['duplicate' => $older->slug])
            ->test(ManageActivityForm::class)
            ->assertSet('name', 'Weekly RPG II'.__('ui.activities.duplicate_name_suffix'))
            ->assertSet('activity_series_id', $series->id)
            ->assertSet('activity_series_name', 'Weekly RPG')
            ->assertSet('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->assertSet('self_hosted_starts_at', $expectedStarts);
    }

    public function test_saving_activity_with_new_series_name_creates_creator_scoped_series(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Fresh Session')
            ->set('activity_type_id', $activityTypeId)
            ->set('activity_series_name', 'Fresh Activity Cycle')
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('search.index'));

        $series = ActivitySeries::query()->where('name', 'Fresh Activity Cycle')->first();
        $this->assertNotNull($series);
        $this->assertSame($user->id, $series->created_by);

        $activity = Activity::query()->where('name', 'Fresh Session')->first();
        $this->assertNotNull($activity);
        $this->assertSame($series->id, $activity->activity_series_id);
    }

    public function test_series_show_page_lists_sessions_including_cancelled(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $series = ActivitySeries::factory()->create([
            'name' => 'Weekly RPG',
            'created_by' => $user->id,
        ]);
        $this->createSelfHostedActivity([
            'created_by' => $user->id,
            'activity_series_id' => $series->id,
            'name' => 'Weekly RPG I',
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonths(2)->addHours(4),
            'cancelled_at' => now()->subMonths(2)->addHour(),
        ]);
        $this->createSelfHostedActivity([
            'created_by' => $user->id,
            'activity_series_id' => $series->id,
            'name' => 'Weekly RPG II',
            'starts_at' => now()->addMonth(),
            'ends_at' => now()->addMonth()->addHours(4),
        ]);

        $this->get(route('activity-series.show', $series))
            ->assertOk()
            ->assertSeeLivewire(ShowActivitySeries::class)
            ->assertSee('Weekly RPG I', false)
            ->assertSee('Weekly RPG II', false);

        Livewire::test(ShowActivitySeries::class, ['activitySeries' => $series])
            ->assertSet('tab', 'activities')
            ->assertSeeHtml('data-ui="activity-series-show-follow"')
            ->assertSee(__('ui.activity_series.stats_sessions'), false)
            ->assertSeeHtml('data-preserve-scroll');
    }

    public function test_owner_manage_menu_creates_activity_by_duplicating_latest_session(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $owner = User::factory()->create();
        $series = ActivitySeries::factory()->create(['created_by' => $owner->id]);
        $older = $this->createSelfHostedActivity([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'activity_series_id' => $series->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(4),
        ]);
        $latest = $this->createSelfHostedActivity([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'activity_series_id' => $series->id,
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
        ]);

        $html = Livewire::actingAs($owner)
            ->test(ShowActivitySeries::class, ['activitySeries' => $series])
            ->html();

        $this->assertStringContainsString('data-ui="activity-series-show-manage"', $html);
        $this->assertStringContainsString('data-ui="activity-series-show-create-activity"', $html);
        $this->assertStringContainsString('data-ui="activity-series-show-delete"', $html);
        $this->assertStringContainsString(route('activities.create', ['duplicate' => $latest->slug]), $html);
        $this->assertStringNotContainsString(route('activities.create', ['duplicate' => $older->slug]), $html);
    }

    public function test_stranger_does_not_see_series_manage_menu(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $series = ActivitySeries::factory()->create(['created_by' => $owner->id]);
        $this->createSelfHostedActivity([
            'created_by' => $owner->id,
            'activity_series_id' => $series->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(3),
        ]);

        Livewire::actingAs($stranger)
            ->test(ShowActivitySeries::class, ['activitySeries' => $series])
            ->assertDontSeeHtml('data-ui="activity-series-show-manage"')
            ->assertDontSeeHtml('data-ui="activity-series-show-delete"');
    }

    public function test_private_series_is_hidden_from_strangers(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $owner = User::factory()->create();
        $series = ActivitySeries::factory()->create([
            'created_by' => $owner->id,
        ]);
        Activity::factory()->create([
            'created_by' => $owner->id,
            'activity_series_id' => $series->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        $this->get(route('activity-series.show', $series))->assertNotFound();

        $this->actingAs($owner)
            ->get(route('activity-series.show', $series))
            ->assertOk();
    }

    public function test_activity_previous_and_next_in_series(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $series = ActivitySeries::factory()->create(['created_by' => $user->id]);
        $first = $this->createSelfHostedActivity([
            'created_by' => $user->id,
            'activity_series_id' => $series->id,
            'name' => 'A',
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(1)->addHours(3),
        ]);
        $second = $this->createSelfHostedActivity([
            'created_by' => $user->id,
            'activity_series_id' => $series->id,
            'name' => 'B',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(3),
        ]);
        $third = $this->createSelfHostedActivity([
            'created_by' => $user->id,
            'activity_series_id' => $series->id,
            'name' => 'C',
            'starts_at' => now()->addDays(20),
            'ends_at' => now()->addDays(20)->addHours(3),
            'cancelled_at' => now(),
        ]);

        $this->assertNull($first->previousInSeries());
        $this->assertSame($second->id, $first->nextInSeries()?->id);
        $this->assertSame($first->id, $second->previousInSeries()?->id);
        $this->assertSame($third->id, $second->nextInSeries()?->id);
        $this->assertTrue($second->nextInSeries()?->isCancelled());
    }

    public function test_listing_card_includes_series_link_for_activities(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $series = ActivitySeries::factory()->create([
            'name' => 'Weekly RPG',
            'created_by' => $user->id,
        ]);
        $activity = $this->createSelfHostedActivity([
            'created_by' => $user->id,
            'activity_series_id' => $series->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(3),
        ]);

        $card = app(BrowseListingCardPresenter::class)->fromActivity($activity, []);

        $this->assertSame('Weekly RPG', $card->seriesName);
        $this->assertSame(route('activity-series.show', $series), $card->seriesUrl);
    }

    public function test_owner_can_delete_series_without_deleting_activities(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $owner = User::factory()->create();
        $series = ActivitySeries::factory()->create(['created_by' => $owner->id]);
        $activity = $this->createSelfHostedActivity([
            'created_by' => $owner->id,
            'activity_series_id' => $series->id,
            'name' => 'Kept Session',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(3),
        ]);

        Livewire::actingAs($owner)
            ->test(ShowActivitySeries::class, ['activitySeries' => $series])
            ->call('confirmDeleteSeries')
            ->call('runConfirmedAction')
            ->assertRedirect(route('search.index'));

        $this->assertSoftDeleted($series);
        $activity->refresh();
        $this->assertNull($activity->activity_series_id);
        $this->assertNull($activity->deleted_at);
    }

    public function test_stranger_cannot_delete_series(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $series = ActivitySeries::factory()->create(['created_by' => $owner->id]);
        $this->createSelfHostedActivity([
            'created_by' => $owner->id,
            'activity_series_id' => $series->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(3),
        ]);

        Livewire::actingAs($stranger)
            ->test(ShowActivitySeries::class, ['activitySeries' => $series])
            ->call('deleteSeries')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_series', ['id' => $series->id, 'deleted_at' => null]);
    }

    public function test_follow_toggles_interest_on_upcoming_sessions_only(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $series = ActivitySeries::factory()->create(['created_by' => $owner->id]);
        $past = $this->createSelfHostedActivity([
            'created_by' => $owner->id,
            'activity_series_id' => $series->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subMonth()->addHours(4),
        ]);
        $upcomingA = $this->createSelfHostedActivity([
            'created_by' => $owner->id,
            'activity_series_id' => $series->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(4),
        ]);
        $upcomingB = $this->createSelfHostedActivity([
            'created_by' => $owner->id,
            'activity_series_id' => $series->id,
            'starts_at' => now()->addDays(20),
            'ends_at' => now()->addDays(20)->addHours(4),
        ]);

        $component = Livewire::actingAs($follower)
            ->test(ShowActivitySeries::class, ['activitySeries' => $series])
            ->assertSeeHtml('data-ui="activity-series-show-follow"');

        $component->call('toggleUpcomingInterest');
        $this->assertTrue($follower->interestedActivities()->whereKey($upcomingA->id)->exists());
        $this->assertTrue($follower->interestedActivities()->whereKey($upcomingB->id)->exists());
        $this->assertFalse($follower->interestedActivities()->whereKey($past->id)->exists());

        $component->call('toggleUpcomingInterest');
        $this->assertFalse($follower->interestedActivities()->whereKey($upcomingA->id)->exists());
        $this->assertFalse($follower->interestedActivities()->whereKey($upcomingB->id)->exists());
    }
}
