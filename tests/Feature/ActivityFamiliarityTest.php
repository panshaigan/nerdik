<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FamiliarityLevel;
use App\Enums\ParticipationMode;
use App\Enums\UserRequestStatus;
use App\Livewire\Activities\ManageActivityForm;
use App\Livewire\Activities\ShowActivity;
use App\Livewire\Notifications\RespondToUserRequest;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Models\User;
use App\Models\UserFamiliarity;
use App\Models\UserRequest;
use App\Services\ActivityFamiliarityService;
use App\Services\EventActivitySignupService;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityFamiliarityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ActivityTypeSeeder::class);
    }

    public function test_host_can_persist_collect_familiarity_toggle(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'collect_familiarity' => false,
        ]);

        Livewire::actingAs($host)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->set('collect_familiarity', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue((bool) $activity->fresh()->collect_familiarity);
    }

    public function test_join_without_collect_familiarity_skips_prompt_and_stores_no_snapshot(): void
    {
        [$host, $member, $activity] = $this->openSelfHostedActivity(collectFamiliarity: false);

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('join')
            ->assertSet('familiarityModalOpen', false);

        $participant = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($participant);
        $this->assertNull($participant->familiarity);
    }

    public function test_join_with_familiarity_answers_stores_memory_and_snapshot(): void
    {
        [$host, $member, $activity, $gameTag] = $this->openSelfHostedActivityWithGameTag();

        $typeKey = app(ActivityFamiliarityService::class)->subjectKey(
            (new ActivityType)->getMorphClass(),
            (int) $activity->activity_type_id,
        );
        $tagKey = app(ActivityFamiliarityService::class)->subjectKey(
            $gameTag->getMorphClass(),
            (int) $gameTag->id,
        );

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('join')
            ->assertSet('familiarityModalOpen', true)
            ->set('familiarityAnswers', [
                $typeKey => FamiliarityLevel::Beginner->value,
                $tagKey => FamiliarityLevel::Experienced->value,
            ])
            ->call('submitFamiliarityPrompt')
            ->assertSet('familiarityModalOpen', false);

        $participant = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($participant);
        $this->assertSame(FamiliarityLevel::Beginner->value, $participant->familiarity['activity_type_level'] ?? null);
        $this->assertSame(
            FamiliarityLevel::Experienced->value,
            collect($participant->familiarity['tags'] ?? [])->firstWhere('tag_id', $gameTag->id)['level'] ?? null,
        );

        $this->assertDatabaseHas('user_familiarities', [
            'user_id' => $member->id,
            'subject_type' => (new ActivityType)->getMorphClass(),
            'subject_id' => $activity->activity_type_id,
            'level' => FamiliarityLevel::Beginner->value,
        ]);
        $this->assertDatabaseHas('user_familiarities', [
            'user_id' => $member->id,
            'subject_type' => $gameTag->getMorphClass(),
            'subject_id' => $gameTag->id,
            'level' => FamiliarityLevel::Experienced->value,
        ]);
    }

    public function test_skip_all_still_joins_without_memory_writes(): void
    {
        [$host, $member, $activity] = $this->openSelfHostedActivityWithGameTag();

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('join')
            ->assertSet('familiarityModalOpen', true)
            ->call('skipFamiliarityPrompt')
            ->assertSet('familiarityModalOpen', false);

        $this->assertTrue(
            ActivityUser::query()->where('activity_id', $activity->id)->where('user_id', $member->id)->exists()
        );
        $this->assertSame(0, UserFamiliarity::query()->where('user_id', $member->id)->count());

        $participant = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->first();
        $this->assertNull($participant?->familiarity);
    }

    public function test_waitlist_signup_stores_snapshot_and_promote_copies_it(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::HostApproval,
            'collect_familiarity' => true,
            'max_participants' => 5,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);

        $typeKey = app(ActivityFamiliarityService::class)->subjectKey(
            (new ActivityType)->getMorphClass(),
            (int) $activity->activity_type_id,
        );

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('joinWaitlist')
            ->assertSet('familiarityModalOpen', true)
            ->set('familiarityAnswers', [
                $typeKey => FamiliarityLevel::None->value,
            ])
            ->call('submitFamiliarityPrompt');

        $entry = ActivityWaitlistEntry::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(FamiliarityLevel::None->value, $entry->familiarity['activity_type_level'] ?? null);

        app(EventActivitySignupService::class)->hostApproveWaitlistEntry($activity, $entry);

        $participant = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $member->id)
            ->first();

        $this->assertNotNull($participant);
        $this->assertSame(FamiliarityLevel::None->value, $participant->familiarity['activity_type_level'] ?? null);
        $this->assertFalse(
            ActivityWaitlistEntry::query()->where('activity_id', $activity->id)->where('user_id', $member->id)->exists()
        );
    }

    public function test_second_join_prefills_saved_levels(): void
    {
        [$host, $member, $activity] = $this->openSelfHostedActivityWithGameTag();

        $typeMorph = (new ActivityType)->getMorphClass();
        UserFamiliarity::query()->create([
            'user_id' => $member->id,
            'subject_type' => $typeMorph,
            'subject_id' => $activity->activity_type_id,
            'level' => FamiliarityLevel::Experienced,
        ]);

        $typeKey = app(ActivityFamiliarityService::class)->subjectKey(
            $typeMorph,
            (int) $activity->activity_type_id,
        );

        Livewire::actingAs($member)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('join')
            ->assertSet('familiarityModalOpen', true)
            ->assertSet('familiarityAnswers.'.$typeKey, FamiliarityLevel::Experienced->value);
    }

    public function test_invite_accept_with_familiarity_stores_snapshot(): void
    {
        $host = User::factory()->create();
        $invitee = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'collect_familiarity' => true,
            'max_participants' => 5,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);

        $request = UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $invitee->id,
            'subject_type' => $activity->getMorphClass(),
            'subject_id' => $activity->id,
            'status' => UserRequestStatus::Pending,
        ]);

        $typeKey = app(ActivityFamiliarityService::class)->subjectKey(
            (new ActivityType)->getMorphClass(),
            (int) $activity->activity_type_id,
        );

        Livewire::actingAs($invitee)
            ->test(RespondToUserRequest::class)
            ->call('openModal', $request->id)
            ->call('accept')
            ->assertSet('familiarityModalOpen', true)
            ->set('familiarityAnswers', [
                $typeKey => FamiliarityLevel::Beginner->value,
            ])
            ->call('submitFamiliarityPrompt');

        $this->assertSame(UserRequestStatus::Accepted, $request->fresh()->status);

        $participant = ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $invitee->id)
            ->first();

        $this->assertNotNull($participant);
        $this->assertSame(FamiliarityLevel::Beginner->value, $participant->familiarity['activity_type_level'] ?? null);
    }

    public function test_familiarity_never_blocks_signup_gate(): void
    {
        [$host, $member, $activity] = $this->openSelfHostedActivityWithGameTag();

        app(EventActivitySignupService::class)->assertCanSignup($activity, $member);

        $this->assertTrue(true);
    }

    public function test_host_roster_shows_familiarity_summary(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create(['nickname' => 'FamiliarMember']);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'collect_familiarity' => true,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);

        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $member->id,
            'familiarity' => [
                'activity_type_id' => $activity->activity_type_id,
                'activity_type_level' => FamiliarityLevel::Beginner->value,
                'tags' => [],
            ],
        ]);

        Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->set('tab', 'participation')
            ->assertSeeHtml('data-ui="familiarity-summary"')
            ->assertSee(FamiliarityLevel::Beginner->label(), false);
    }

    /**
     * @return array{0: User, 1: User, 2: Activity}
     */
    private function openSelfHostedActivity(bool $collectFamiliarity): array
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'collect_familiarity' => $collectFamiliarity,
            'max_participants' => 5,
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
        ]);

        return [$host, $member, $activity];
    }

    /**
     * @return array{0: User, 1: User, 2: Activity, 3: Tag}
     */
    private function openSelfHostedActivityWithGameTag(): array
    {
        [$host, $member, $activity] = $this->openSelfHostedActivity(collectFamiliarity: true);

        $gameCategory = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $gameTag = Tag::factory()->create(['tag_category_id' => $gameCategory->id]);
        TagTranslation::factory()->create([
            'tag_id' => $gameTag->id,
            'locale' => 'en',
            'label' => 'Test Game',
        ]);
        $activity->tags()->attach([$gameTag->id]);

        return [$host, $member, $activity->fresh(), $gameTag];
    }
}
