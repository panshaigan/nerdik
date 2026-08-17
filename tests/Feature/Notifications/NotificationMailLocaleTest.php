<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\AppLocale;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\SentEmail;
use App\Models\User;
use App\Notifications\Scheduled\ScheduledPeriodicDigestNotification;
use App\Notifications\VerifyPendingEmailNotification;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NotificationMailLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('email_logs');
    }

    public function test_preferred_locale_falls_back_to_english_when_unset(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->assertNull($user->locale);
        $this->assertSame(AppLocale::En->value, $user->preferredLocale());
    }

    public function test_queued_notification_mail_uses_recipient_locale_not_app_locale(): void
    {
        app()->setLocale('en');

        $user = User::factory()->locale(AppLocale::Pl)->create();
        $activity = Activity::factory()->create(['name' => 'Chess Club']);

        $user->notify(new WaitlistPromotedNotification($activity));

        $row = SentEmail::query()->first();

        $this->assertNotNull($row);
        $this->assertStringContainsString('Masz miejsce w: Chess Club', (string) $row->subject);
        $this->assertStringContainsString('Cześć', (string) $row->htmlBody());
        $this->assertSame('en', app()->getLocale());
    }

    public function test_queued_notification_mail_stays_english_when_app_locale_is_polish(): void
    {
        app()->setLocale('pl');

        $user = User::factory()->locale(AppLocale::En)->create();
        $activity = Activity::factory()->create(['name' => 'Chess Club']);

        $user->notify(new WaitlistPromotedNotification($activity));

        $row = SentEmail::query()->first();

        $this->assertNotNull($row);
        $this->assertStringContainsString('You got a place on Chess Club', (string) $row->subject);
        $this->assertStringContainsString('Hello', (string) $row->htmlBody());
        $this->assertSame('pl', app()->getLocale());
    }

    public function test_pending_email_notification_uses_user_locale(): void
    {
        app()->setLocale('en');

        $user = User::factory()->locale(AppLocale::Pl)->create([
            'pending_email' => 'new@example.com',
        ]);

        Notification::fake();

        $user->sendPendingEmailVerificationNotification();

        Notification::assertSentOnDemand(
            VerifyPendingEmailNotification::class,
            function (VerifyPendingEmailNotification $notification): bool {
                return $notification->locale === AppLocale::Pl->value;
            }
        );
    }

    public function test_pending_email_mail_body_is_translated_for_polish_user(): void
    {
        app()->setLocale('en');

        $user = User::factory()->locale(AppLocale::Pl)->create([
            'pending_email' => 'new@example.com',
        ]);

        $user->sendPendingEmailVerificationNotification();

        $row = SentEmail::query()->first();

        $this->assertNotNull($row);
        $this->assertStringContainsString('Zweryfikuj nowy adres e-mail', (string) $row->subject);
    }

    public function test_scheduled_digest_collects_lines_in_recipient_locale(): void
    {
        config()->set('scheduled_notifications.daily_send_time', '09:00');
        $this->travelTo('2026-06-01 09:00:00');
        app()->setLocale('en');

        $user = User::factory()->locale(AppLocale::Pl)->create();
        $user->profile()->update(['timezone' => 'UTC']);
        $organizer = User::factory()->create();

        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'name' => 'One More Game',
            'starts_at' => now()->addDays(30),
            'ends_at' => now()->addDays(31),
        ]);
        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->addHours(12),
            'ends_at' => now()->addDays(2),
        ]);
        $user->interestedEvents()->attach($event->id);

        Notification::fake();

        $this->artisan('notifications:scheduled-digest')->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            ScheduledPeriodicDigestNotification::class,
            function (ScheduledPeriodicDigestNotification $notification) use ($user): bool {
                $items = $notification->toArray($user)['items'] ?? [];

                return collect($items)->contains(
                    fn (array $item): bool => str_contains((string) ($item['title'] ?? ''), 'Okno zapisów: One More Game')
                );
            }
        );

        $this->assertSame('en', app()->getLocale());
    }
}
