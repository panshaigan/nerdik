<?php

declare(strict_types=1);

namespace App\Actions\E2E;

use App\Enums\ActivityProposalStatus;
use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\ProposalSubmittedNotification;
use Database\Factories\DatabaseNotificationFactory;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class PrepareE2EFixtures
{
    public const PLAYER_EMAIL = 'e2e.player@nerdik.test';

    public const ORGANIZER_EMAIL = 'e2e.organizer@nerdik.test';

    public const PASSWORD = 'password';

    public const EVENT_NAME = 'E2E Playwright Join Con';

    public const EVENT_SLUG = 'e2e-playwright-join-con';

    public const ACTIVITY_NAME = 'E2E Playwright Open Table';

    public const ACTIVITY_SLUG = 'e2e-playwright-open-table';

    public const WAITLIST_ACTIVITY_NAME = 'E2E Playwright Waitlist Table';

    public const WAITLIST_ACTIVITY_SLUG = 'e2e-playwright-waitlist-table';

    public const DRAFT_ACTIVITY_NAME = 'E2E Playwright Draft Proposal';

    public const DRAFT_ACTIVITY_SLUG = 'e2e-playwright-draft-proposal';

    public const PENDING_PROPOSAL_ACTIVITY_NAME = 'E2E Playwright Pending Proposal';

    public const PENDING_PROPOSAL_ACTIVITY_SLUG = 'e2e-playwright-pending-proposal';

    public const PLACE_NAME = 'E2E Playwright Venue';

    public const PLACE_SLUG = 'e2e-playwright-venue';

    public const PLACE_LAT = 51.1102;

    public const PLACE_LNG = 17.0323;

    public const ENROLLMENT_NAME = 'E2E Playwright Enrollment';

    public const OCCUPIED_SLOT_NAME = 'E2E Table';

    public const EMPTY_SLOT_NAME = 'E2E Empty Slot';

    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        (new ActivityTypeSeeder)->run();

        $organizer = $this->upsertUser(
            email: self::ORGANIZER_EMAIL,
            nickname: 'e2e-organizer',
            name: 'E2E Organizer',
            organizer: true,
        );
        $player = $this->upsertUser(
            email: self::PLAYER_EMAIL,
            nickname: 'e2e-player',
            name: 'E2E Player',
            organizer: false,
        );

        $place = $this->upsertPlace($organizer);
        $event = $this->upsertEvent($organizer, $place);
        $this->upsertEnrollmentWindow($event, $organizer);
        $activity = $this->upsertActivity($organizer, $event, $place);
        $occupiedSlot = Slot::query()->where('activity_id', $activity->id)->firstOrFail();
        $emptySlot = $this->upsertEmptySlot($event, $place, $organizer);
        $waitlistActivity = $this->upsertWaitlistActivity($organizer, $event, $place);
        $draftActivity = $this->upsertDraftActivity($player);
        $pendingProposal = $this->upsertPendingProposal($player, $event, $place);
        $notification = $this->upsertOrganizerNotification($organizer, $pendingProposal);

        $this->resetPlayerParticipation($activity, $player);
        $this->resetPlayerParticipation($waitlistActivity, $player);

        $payload = [
            'player' => [
                'email' => self::PLAYER_EMAIL,
                'password' => self::PASSWORD,
            ],
            'organizer' => [
                'email' => self::ORGANIZER_EMAIL,
                'password' => self::PASSWORD,
            ],
            'event' => [
                'name' => $event->name,
                'slug' => $event->slug,
                'path' => '/events/'.$event->slug,
                'edit_path' => '/events/'.$event->slug.'/edit',
            ],
            'activity' => [
                'name' => $activity->name,
                'slug' => $activity->slug,
                'path' => '/activities/'.$activity->slug,
            ],
            'slot' => [
                'id' => $occupiedSlot->id,
                'name' => $occupiedSlot->name,
            ],
            'empty_slot' => [
                'id' => $emptySlot->id,
                'name' => $emptySlot->name,
            ],
            'waitlist_activity' => [
                'name' => $waitlistActivity->name,
                'slug' => $waitlistActivity->slug,
                'path' => '/activities/'.$waitlistActivity->slug,
            ],
            'draft_activity' => [
                'name' => $draftActivity->name,
                'slug' => $draftActivity->slug,
                'path' => '/activities/'.$draftActivity->slug,
            ],
            'pending_proposal' => [
                'activity_name' => $pendingProposal->activity->name,
                'activity_slug' => $pendingProposal->activity->slug,
                'proposal_id' => $pendingProposal->id,
            ],
            'notification' => [
                'id' => $notification->id,
                'expected_path' => '/events/'.$event->slug.'?tab=proposals',
            ],
            'place' => [
                'name' => $place->name,
                'latitude' => self::PLACE_LAT,
                'longitude' => self::PLACE_LNG,
            ],
            'map' => [
                'path' => '/search?map_view=1&q='.rawurlencode(self::EVENT_NAME).'&min_lat=51.05&max_lat=51.16&min_lng=16.97&max_lng=17.09',
            ],
        ];

        $this->writeFixtureFile($payload);

        return $payload;
    }

    public static function fixturePath(): string
    {
        return storage_path('app/e2e-fixtures.json');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeFixtureFile(array $payload): void
    {
        $path = self::fixturePath();
        File::ensureDirectoryExists(dirname($path));
        File::put(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    private function upsertUser(string $email, string $nickname, string $name, bool $organizer): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'nickname' => $nickname,
                'password' => self::PASSWORD,
                'is_event_organizer' => $organizer,
            ],
        );

        $user->forceFill([
            'email_verified_at' => now(),
            'is_admin' => false,
            'remember_token' => Str::random(10),
        ])->save();

        if ($user->profile === null) {
            UserProfile::factory()->create(['user_id' => $user->id]);
        }

        return $user;
    }

    private function upsertPlace(User $organizer): Place
    {
        return Place::query()->updateOrCreate(
            ['slug' => self::PLACE_SLUG],
            [
                'name' => self::PLACE_NAME,
                'type' => Place::TYPE_VENUE,
                'latitude' => self::PLACE_LAT,
                'longitude' => self::PLACE_LNG,
                'created_by' => $organizer->id,
                'updated_by' => $organizer->id,
            ],
        );
    }

    private function upsertEvent(User $organizer, Place $place): Event
    {
        $startsAt = now()->addDays(14)->setTime(12, 0, 0);
        $endsAt = $startsAt->copy()->addHours(8);

        $event = Event::query()->updateOrCreate(
            ['slug' => self::EVENT_SLUG],
            [
                'name' => self::EVENT_NAME,
                'is_public' => true,
                'description' => 'Deterministic Playwright fixture event.',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => $organizer->id,
                'updated_by' => $organizer->id,
                'organization_id' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancel_reason' => null,
            ],
        );

        $event->places()->syncWithoutDetaching([$place->id]);

        return $event;
    }

    private function upsertEnrollmentWindow(Event $event, User $organizer): void
    {
        $window = EventEnrollmentWindow::query()->firstOrNew([
            'event_id' => $event->id,
            'name' => self::ENROLLMENT_NAME,
        ]);

        $window->forceFill([
            'starts_at' => now()->subDay(),
            'ends_at' => $event->ends_at ?? now()->addDays(15),
            'max_activities_per_user' => 0,
            'max_allowed_participants_per_activity' => 0,
            'accumulative_activities' => false,
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ])->save();
    }

    private function upsertActivity(User $organizer, Event $event, Place $place): Activity
    {
        $typeId = $this->defaultActivityTypeId();

        $startsAt = $event->starts_at?->copy()->setTime(11, 30, 0) ?? now()->addDays(14)->setTime(11, 30, 0);
        $endsAt = $startsAt->copy()->addHours(4);

        $activity = Activity::query()->updateOrCreate(
            ['slug' => self::ACTIVITY_SLUG],
            [
                'name' => self::ACTIVITY_NAME,
                'description' => 'Deterministic Playwright fixture activity.',
                'activity_type_id' => $typeId,
                'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
                'participation_mode' => ParticipationMode::Open,
                'collect_familiarity' => false,
                'allows_observers' => false,
                'min_participants' => 1,
                'max_participants' => 6,
                'duration_in_minutes' => 240,
                'created_by' => $organizer->id,
                'updated_by' => $organizer->id,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancel_reason' => null,
            ],
        );

        Slot::query()->updateOrCreate(
            ['activity_id' => $activity->id],
            [
                'event_id' => $event->id,
                'name' => self::OCCUPIED_SLOT_NAME,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'place_id' => $place->id,
                'created_by' => $organizer->id,
                'max_capacity' => 6,
                'requires_approval' => false,
            ],
        );

        return $activity;
    }

    private function upsertEmptySlot(Event $event, Place $place, User $organizer): Slot
    {
        $startsAt = $event->starts_at?->copy()->setTime(16, 0, 0) ?? now()->addDays(14)->setTime(16, 0, 0);
        $endsAt = $startsAt->copy()->addHours(4);

        $slot = Slot::query()->updateOrCreate(
            [
                'event_id' => $event->id,
                'name' => self::EMPTY_SLOT_NAME,
            ],
            [
                'activity_id' => null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'place_id' => $place->id,
                'created_by' => $organizer->id,
                'max_capacity' => 6,
                'requires_approval' => false,
            ],
        );

        $slot->activityTypes()->sync([]);

        return $slot;
    }

    private function upsertWaitlistActivity(User $organizer, Event $event, Place $place): Activity
    {
        $typeId = $this->defaultActivityTypeId();
        $startsAt = $event->starts_at?->copy()->setTime(16, 0, 0) ?? now()->addDays(14)->setTime(16, 0, 0);
        $endsAt = $startsAt->copy()->addHours(4);

        $activity = Activity::query()->updateOrCreate(
            ['slug' => self::WAITLIST_ACTIVITY_SLUG],
            [
                'name' => self::WAITLIST_ACTIVITY_NAME,
                'description' => 'Playwright waitlist fixture activity.',
                'activity_type_id' => $typeId,
                'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
                'participation_mode' => ParticipationMode::HostApproval,
                'collect_familiarity' => false,
                'allows_observers' => false,
                'min_participants' => 1,
                'max_participants' => 6,
                'duration_in_minutes' => 240,
                'created_by' => $organizer->id,
                'updated_by' => $organizer->id,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancel_reason' => null,
            ],
        );

        Slot::query()->updateOrCreate(
            ['activity_id' => $activity->id],
            [
                'event_id' => $event->id,
                'name' => 'E2E Waitlist Slot',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'place_id' => $place->id,
                'created_by' => $organizer->id,
                'max_capacity' => 6,
                'requires_approval' => false,
            ],
        );

        return $activity;
    }

    private function upsertDraftActivity(User $player): Activity
    {
        $typeId = $this->defaultActivityTypeId();

        return Activity::query()->updateOrCreate(
            ['slug' => self::DRAFT_ACTIVITY_SLUG],
            [
                'name' => self::DRAFT_ACTIVITY_NAME,
                'description' => 'Playwright draft activity for proposal flows.',
                'activity_type_id' => $typeId,
                'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
                'participation_mode' => ParticipationMode::Open,
                'collect_familiarity' => false,
                'allows_observers' => false,
                'min_participants' => 1,
                'max_participants' => 6,
                'duration_in_minutes' => 240,
                'created_by' => $player->id,
                'updated_by' => $player->id,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancel_reason' => null,
            ],
        );
    }

    private function upsertPendingProposal(User $player, Event $event, Place $place): ActivityProposal
    {
        $typeId = $this->defaultActivityTypeId();

        $activity = Activity::query()->updateOrCreate(
            ['slug' => self::PENDING_PROPOSAL_ACTIVITY_SLUG],
            [
                'name' => self::PENDING_PROPOSAL_ACTIVITY_NAME,
                'description' => 'Playwright pending proposal fixture activity.',
                'activity_type_id' => $typeId,
                'hosting_mode' => Activity::HOSTING_MODE_PROPOSED_TO_EVENT,
                'participation_mode' => ParticipationMode::Open,
                'collect_familiarity' => false,
                'allows_observers' => false,
                'min_participants' => 1,
                'max_participants' => 5,
                'duration_in_minutes' => 240,
                'created_by' => $player->id,
                'updated_by' => $player->id,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancel_reason' => null,
            ],
        );

        Slot::query()
            ->where('activity_id', $activity->id)
            ->update(['activity_id' => null]);

        $proposal = ActivityProposal::query()->updateOrCreate(
            ['activity_id' => $activity->id],
            [
                'event_id' => $event->id,
                'status' => ActivityProposalStatus::Pending,
                'accepted_slot_id' => null,
                'created_by' => $player->id,
                'updated_by' => $player->id,
            ],
        );

        return $proposal->load(['activity', 'event']);
    }

    private function upsertOrganizerNotification(User $organizer, ActivityProposal $proposal): DatabaseNotification
    {
        $organizer->notifications()->delete();

        return DatabaseNotificationFactory::new()
            ->for($organizer, 'notifiable')
            ->fromNotification(new ProposalSubmittedNotification($proposal), $organizer)
            ->unread()
            ->create();
    }

    private function defaultActivityTypeId(): int
    {
        return (int) (ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id
            ?? ActivityType::query()->value('id'));
    }

    private function resetPlayerParticipation(Activity $activity, User $player): void
    {
        ActivityUser::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $player->id)
            ->delete();

        ActivityWaitlistEntry::query()
            ->where('activity_id', $activity->id)
            ->where('user_id', $player->id)
            ->delete();
    }
}
