<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\Scheduled\ScheduledPeriodicDigestNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledDigestPreferenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{category: string, title: string, lines: list<string>, url: string, dedupe_key: string}
     */
    private function sampleInterestedEnrollmentItem(): array
    {
        return [
            'category' => 'interested_enrollment_window',
            'title' => 'Enrollment window',
            'lines' => ['line'],
            'url' => 'https://example.test/e',
            'dedupe_key' => 'interested_enrollment_window:1:0:2026-06-01',
        ];
    }

    public function test_digest_via_is_mail_only_when_in_app_disabled_for_items(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'scheduled_interested_enrollment_window' => [
                    'in_app' => false,
                    'email' => true,
                ],
            ],
        ]);

        $notification = new ScheduledPeriodicDigestNotification([$this->sampleInterestedEnrollmentItem()], '2026-06-01');

        $this->assertSame(['mail'], $notification->via($user));
        $this->assertSame([], $notification->toArray($user)['items']);
    }

    public function test_digest_via_is_database_only_when_email_disabled_for_items(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'scheduled_interested_enrollment_window' => [
                    'in_app' => true,
                    'email' => false,
                ],
            ],
        ]);

        $item = $this->sampleInterestedEnrollmentItem();
        $notification = new ScheduledPeriodicDigestNotification([$item], '2026-06-01');

        $this->assertSame(['database'], $notification->via($user));
        $payload = $notification->toArray($user);
        $this->assertCount(1, $payload['items']);
        $this->assertSame(
            __('ui.notifications.scheduled.digest_toast_description', ['count' => 1]),
            $payload['toast_description']
        );
    }

    public function test_digest_returns_empty_via_when_user_disables_both_channels_for_all_items(): void
    {
        $user = User::factory()->create([
            'notification_preferences' => [
                'scheduled_interested_enrollment_window' => [
                    'in_app' => false,
                    'email' => false,
                ],
            ],
        ]);

        $notification = new ScheduledPeriodicDigestNotification([$this->sampleInterestedEnrollmentItem()], '2026-06-01');

        $this->assertSame([], $notification->via($user));
    }
}
