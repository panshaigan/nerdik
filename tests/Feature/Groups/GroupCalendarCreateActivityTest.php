<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Enums\ParticipationMode;
use App\Livewire\Activities\ManageActivityForm;
use App\Livewire\Groups\GroupAvailabilityCalendar;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Place;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserAvailabilityWeekday;
use App\Services\GroupCalendarActivityPrefillService;
use App\Services\GroupMembershipService;
use App\Services\UserHostBanService;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class GroupCalendarCreateActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_manage_activity_form_prefills_from_group_calendar_token(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($host, 'Prefill Group');
        $group->users()->attach($member->id);
        $tag = Tag::factory()->create();
        $group->tags()->attach($tag->id);

        $futureDate = now()->addDays(5);
        $weekday = strtolower($futureDate->format('D'));
        UserAvailabilityWeekday::query()->create([
            'user_id' => $member->id,
            $weekday => true,
        ]);

        $token = app(GroupCalendarActivityPrefillService::class)->makeToken(
            $group,
            $futureDate->format('Y-m-d'),
            $host,
        );

        Livewire::actingAs($host)
            ->withQueryParams(['group_calendar' => $token])
            ->test(ManageActivityForm::class)
            ->assertSet('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->assertSet('self_hosted_starts_at', $futureDate->format('Y-m-d').' 18:00')
            ->assertSet('participation_mode', ParticipationMode::HostApproval->value)
            ->assertSet('min_participants', 1)
            ->assertSet('max_participants', 1)
            ->assertSet('tag_ids', [(int) $tag->id])
            ->assertSet('groupCalendarToken', $token);
    }

    public function test_foreign_user_cannot_use_another_hosts_calendar_token(): void
    {
        $host = User::factory()->create();
        $stranger = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($host, 'Token Bound Group');

        $futureDate = now()->addDays(5);
        $token = app(GroupCalendarActivityPrefillService::class)->makeToken(
            $group,
            $futureDate->format('Y-m-d'),
            $host,
        );

        Livewire::actingAs($stranger)
            ->withQueryParams(['group_calendar' => $token])
            ->test(ManageActivityForm::class)
            ->assertSet('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->assertSet('groupCalendarToken', null);
    }

    public function test_day_modal_links_to_create_activity_with_encrypted_token(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Calendar Hosts');

        $futureDate = now()->addDays(5);
        $futureDateKey = $futureDate->format('Y-m-d');

        Livewire::actingAs($owner)
            ->test(GroupAvailabilityCalendar::class, [
                'groupId' => $group->id,
                'month' => $futureDate->format('Y-m'),
            ])
            ->call('selectDate', $futureDateKey)
            ->assertSet('dayDetailOpen', true)
            ->assertSeeHtml('data-ui="group-day-create-activity"')
            ->assertSee('group_calendar=', false)
            ->assertDontSee('tag_ids=', false);
    }

    public function test_day_modal_omits_create_activity_for_past_days(): void
    {
        $owner = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($owner, 'Past Day Group');

        $pastDate = now()->subDays(5);
        $pastDateKey = $pastDate->format('Y-m-d');

        Livewire::actingAs($owner)
            ->test(GroupAvailabilityCalendar::class, [
                'groupId' => $group->id,
                'month' => $pastDate->format('Y-m'),
            ])
            ->call('selectDate', $pastDateKey)
            ->assertSet('dayDetailOpen', true)
            ->assertDontSeeHtml('data-ui="group-day-create-activity"');
    }

    public function test_creating_from_group_calendar_enrolls_available_members_not_host(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $host = User::factory()->create();
        $availableOne = User::factory()->create();
        $availableTwo = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($host, 'Enroll Group');
        $group->users()->attach([$availableOne->id, $availableTwo->id]);

        $futureDate = now()->addDays(5);
        $weekday = strtolower($futureDate->format('D'));
        UserAvailabilityWeekday::query()->create([
            'user_id' => $host->id,
            $weekday => true,
        ]);
        UserAvailabilityWeekday::query()->create([
            'user_id' => $availableOne->id,
            $weekday => true,
        ]);
        UserAvailabilityWeekday::query()->create([
            'user_id' => $availableTwo->id,
            $weekday => true,
        ]);

        $token = app(GroupCalendarActivityPrefillService::class)->makeToken(
            $group,
            $futureDate->format('Y-m-d'),
            $host,
        );

        $venue = Place::factory()->venue()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($host)
            ->withQueryParams(['group_calendar' => $token])
            ->test(ManageActivityForm::class)
            ->set('name', 'Group Day Session')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->set('self_hosted_place_id', $venue->id)
            ->set('self_hosted_starts_at', $futureDate->format('Y-m-d').' 18:00')
            ->set('cancellation_deadline_in_hours', 24)
            ->call('save')
            ->assertHasNoErrors();

        $activity = Activity::query()->where('name', 'Group Day Session')->first();
        $this->assertNotNull($activity);

        $participantIds = $activity->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();
        sort($participantIds);
        $expected = [(int) $availableOne->id, (int) $availableTwo->id];
        sort($expected);

        $this->assertSame($expected, $participantIds);
        $this->assertFalse($activity->waitlist()->exists());
    }

    public function test_creating_from_group_calendar_skips_banned_available_members(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $host = User::factory()->create();
        $bannedMember = User::factory()->create();
        $freeMember = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($host, 'Ban Filter Group');
        $group->users()->attach([$bannedMember->id, $freeMember->id]);

        Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
        ]);
        app(UserHostBanService::class)->ban($host, $bannedMember);

        $futureDate = now()->addDays(5);
        $weekday = strtolower($futureDate->format('D'));
        UserAvailabilityWeekday::query()->create([
            'user_id' => $bannedMember->id,
            $weekday => true,
        ]);
        UserAvailabilityWeekday::query()->create([
            'user_id' => $freeMember->id,
            $weekday => true,
        ]);

        $token = app(GroupCalendarActivityPrefillService::class)->makeToken(
            $group,
            $futureDate->format('Y-m-d'),
            $host,
        );

        $venue = Place::factory()->venue()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($host)
            ->withQueryParams(['group_calendar' => $token])
            ->test(ManageActivityForm::class)
            ->set('name', 'Ban Skip Session')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->set('self_hosted_place_id', $venue->id)
            ->set('self_hosted_starts_at', $futureDate->format('Y-m-d').' 18:00')
            ->set('cancellation_deadline_in_hours', 24)
            ->call('save')
            ->assertHasNoErrors();

        $activity = Activity::query()->where('name', 'Ban Skip Session')->first();
        $this->assertNotNull($activity);
        $this->assertSame(
            [(int) $freeMember->id],
            $activity->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_user_ids_query_param_does_not_add_extra_participants(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $host = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();
        $group = app(GroupMembershipService::class)->create($host, 'No Url Abuse Group');
        $group->users()->attach($member->id);

        $futureDate = now()->addDays(5);
        $weekday = strtolower($futureDate->format('D'));
        UserAvailabilityWeekday::query()->create([
            'user_id' => $member->id,
            $weekday => true,
        ]);

        $token = app(GroupCalendarActivityPrefillService::class)->makeToken(
            $group,
            $futureDate->format('Y-m-d'),
            $host,
        );

        $venue = Place::factory()->venue()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($host)
            ->withQueryParams([
                'group_calendar' => $token,
                'user_ids' => [(int) $outsider->id],
            ])
            ->test(ManageActivityForm::class)
            ->set('name', 'No Outsider Session')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->set('self_hosted_place_id', $venue->id)
            ->set('self_hosted_starts_at', $futureDate->format('Y-m-d').' 18:00')
            ->set('cancellation_deadline_in_hours', 24)
            ->call('save')
            ->assertHasNoErrors();

        $activity = Activity::query()->where('name', 'No Outsider Session')->first();
        $this->assertNotNull($activity);
        $this->assertSame(
            [(int) $member->id],
            $activity->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all(),
        );
    }
}
