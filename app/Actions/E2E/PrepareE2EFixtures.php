<?php

declare(strict_types=1);

namespace App\Actions\E2E;

use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Models\UserProfile;
use Database\Seeders\ActivityTypeSeeder;
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

    public const PLACE_NAME = 'E2E Playwright Venue';

    public const PLACE_SLUG = 'e2e-playwright-venue';

    public const PLACE_LAT = 51.1102;

    public const PLACE_LNG = 17.0323;

    public const ENROLLMENT_NAME = 'E2E Playwright Enrollment';

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
        $this->resetPlayerParticipation($activity, $player);

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
            ],
            'activity' => [
                'name' => $activity->name,
                'slug' => $activity->slug,
                'path' => '/activities/'.$activity->slug,
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
        $typeId = ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id
            ?? ActivityType::query()->value('id');

        $startsAt = $event->starts_at?->copy()->setTime(14, 0, 0) ?? now()->addDays(14)->setTime(14, 0, 0);
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
                'name' => 'E2E Table',
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
