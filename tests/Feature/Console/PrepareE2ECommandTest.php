<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\E2E\PrepareE2EFixtures;
use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PrepareE2ECommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_upserts_join_fixtures_and_writes_json(): void
    {
        $this->artisan('e2e:prepare')
            ->expectsOutputToContain('Wrote Playwright fixtures')
            ->assertSuccessful();

        $organizer = User::query()->where('email', PrepareE2EFixtures::ORGANIZER_EMAIL)->first();
        $player = User::query()->where('email', PrepareE2EFixtures::PLAYER_EMAIL)->first();
        $event = Event::query()->where('slug', PrepareE2EFixtures::EVENT_SLUG)->first();
        $activity = Activity::query()->where('slug', PrepareE2EFixtures::ACTIVITY_SLUG)->first();

        $this->assertNotNull($organizer);
        $this->assertTrue($organizer->is_event_organizer);
        $this->assertTrue(Hash::check(PrepareE2EFixtures::PASSWORD, $organizer->password));
        $this->assertNotNull($organizer->email_verified_at);
        $this->assertNotNull($player);
        $this->assertFalse($player->is_event_organizer);
        $this->assertNotNull($player->email_verified_at);
        $this->assertNotNull($event);
        $this->assertTrue((bool) $event->is_public);
        $this->assertTrue(
            EventEnrollmentWindow::query()
                ->where('event_id', $event->id)
                ->where('name', PrepareE2EFixtures::ENROLLMENT_NAME)
                ->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now())
                ->exists(),
        );
        $this->assertNotNull($activity);
        $this->assertSame(Activity::HOSTING_MODE_SCHEDULED_ON_EVENT, (int) $activity->hosting_mode);
        $this->assertSame(ParticipationMode::Open, $activity->participation_mode);
        $this->assertTrue(
            Slot::query()
                ->where('event_id', $event->id)
                ->where('activity_id', $activity->id)
                ->exists(),
        );

        $path = PrepareE2EFixtures::fixturePath();
        $this->assertFileExists($path);
        $decoded = json_decode((string) File::get($path), true);
        $this->assertIsArray($decoded);
        $this->assertSame(PrepareE2EFixtures::PLAYER_EMAIL, $decoded['player']['email'] ?? null);
        $this->assertSame('/events/'.PrepareE2EFixtures::EVENT_SLUG, $decoded['event']['path'] ?? null);
        $this->assertStringContainsString('map_view=1', (string) ($decoded['map']['path'] ?? ''));
        $this->assertStringContainsString(rawurlencode(PrepareE2EFixtures::EVENT_NAME), (string) ($decoded['map']['path'] ?? ''));
    }

    #[Test]
    public function it_is_idempotent_and_clears_the_player_signup(): void
    {
        $this->artisan('e2e:prepare')->assertSuccessful();

        $player = User::query()->where('email', PrepareE2EFixtures::PLAYER_EMAIL)->firstOrFail();
        $activity = Activity::query()->where('slug', PrepareE2EFixtures::ACTIVITY_SLUG)->firstOrFail();
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $player->id,
        ]);

        $this->artisan('e2e:prepare')->assertSuccessful();

        $this->assertSame(1, User::query()->where('email', PrepareE2EFixtures::ORGANIZER_EMAIL)->count());
        $this->assertSame(1, Event::query()->where('slug', PrepareE2EFixtures::EVENT_SLUG)->count());
        $this->assertFalse(
            ActivityUser::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $player->id)
                ->exists(),
        );
    }

    #[Test]
    public function it_refuses_fresh_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('e2e:prepare', ['--fresh' => true])
            ->expectsOutputToContain('Refusing --fresh in production.')
            ->assertFailed();
    }
}
