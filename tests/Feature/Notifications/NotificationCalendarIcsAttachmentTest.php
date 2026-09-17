<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Notifications\ActivityCancelledNotification;
use App\Notifications\EventCancelledNotification;
use App\Notifications\EventReopenedNotification;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\WaitlistPromotedNotification;
use App\Support\Calendar\IcsMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationCalendarIcsAttachmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_waitlist_promoted_mail_attaches_publish_ics(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create(['name' => 'Hall']);
        $startsAt = now()->addDays(3)->utc()->startOfMinute();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'name' => 'Promoted Session',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
        ]);

        $mail = (new WaitlistPromotedNotification($activity))->toMail($user);
        $attachment = $this->assertHasIcsAttachment($mail, 'promoted-session.ics');

        $this->assertStringContainsString('METHOD:PUBLISH', $attachment['data']);
        $this->assertStringContainsString('STATUS:CONFIRMED', $attachment['data']);
        $this->assertStringContainsString('UID:activity-'.$activity->id.'@', $attachment['data']);
        $this->assertSame(
            'text/calendar; charset=UTF-8; method=PUBLISH',
            $attachment['options']['mime'],
        );
    }

    public function test_proposal_accepted_mail_attaches_activity_ics_when_scheduled(): void
    {
        $host = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addWeek()->utc()->startOfMinute();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'name' => 'Accepted Panel',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(1),
        ]);
        $event = Event::factory()->public()->create(['created_by' => $host->id]);
        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'status' => 'accepted',
            'created_by' => $host->id,
        ]);
        $proposal->load(['activity', 'event']);

        $mail = (new ProposalAcceptedNotification($proposal))->toMail($host);
        $attachment = $this->assertHasIcsAttachment($mail, 'accepted-panel.ics');

        $this->assertStringContainsString('METHOD:PUBLISH', $attachment['data']);
        $this->assertStringContainsString('SUMMARY:Accepted Panel', $attachment['data']);
    }

    public function test_activity_cancelled_mail_attaches_cancel_ics_with_same_uid(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(4)->utc()->startOfMinute();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'name' => 'Cancelled Night',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'cancelled_at' => now(),
            'cancelled_by' => $host->id,
        ]);

        $mail = (new ActivityCancelledNotification($activity, $host))->toMail($participant);
        $attachment = $this->assertHasIcsAttachment($mail, 'cancelled-night-cancelled.ics');

        $this->assertStringContainsString('METHOD:CANCEL', $attachment['data']);
        $this->assertStringContainsString('STATUS:CANCELLED', $attachment['data']);
        $this->assertStringContainsString('SEQUENCE:1', $attachment['data']);
        $this->assertStringContainsString('UID:activity-'.$activity->id.'@', $attachment['data']);
        $this->assertSame(
            'text/calendar; charset=UTF-8; method='.IcsMethod::Cancel->value,
            $attachment['options']['mime'],
        );
    }

    public function test_event_cancelled_mail_attaches_cancel_ics(): void
    {
        $organizer = User::factory()->create();
        $recipient = User::factory()->create();
        $startsAt = now()->addWeek()->utc()->startOfMinute();
        $event = Event::factory()->public()->create([
            'created_by' => $organizer->id,
            'name' => 'Cancelled Con',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays(2),
            'cancelled_at' => now(),
            'cancelled_by' => $organizer->id,
        ]);

        $mail = (new EventCancelledNotification(
            (int) $event->id,
            (string) $event->name,
            (string) $event->slug,
        ))->toMail($recipient);

        $attachment = $this->assertHasIcsAttachment($mail, 'cancelled-con-cancelled.ics');
        $this->assertStringContainsString('METHOD:CANCEL', $attachment['data']);
        $this->assertStringContainsString('UID:event-'.$event->id.'@', $attachment['data']);
    }

    public function test_event_reopened_mail_attaches_publish_ics(): void
    {
        $organizer = User::factory()->create();
        $recipient = User::factory()->create();
        $startsAt = now()->addWeek()->utc()->startOfMinute();
        $event = Event::factory()->public()->create([
            'created_by' => $organizer->id,
            'name' => 'Reopened Con',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDay(),
        ]);

        $mail = (new EventReopenedNotification($event, $organizer))->toMail($recipient);
        $attachment = $this->assertHasIcsAttachment($mail, 'reopened-con.ics');

        $this->assertStringContainsString('METHOD:PUBLISH', $attachment['data']);
        $this->assertStringContainsString('STATUS:CONFIRMED', $attachment['data']);
    }

    /**
     * @return array{data: string, name: string, options: array<string, mixed>}
     */
    private function assertHasIcsAttachment(object $mail, string $expectedName): array
    {
        $attachments = $mail->rawAttachments;
        $this->assertNotEmpty($attachments);

        $match = collect($attachments)->first(
            fn (array $attachment): bool => ($attachment['name'] ?? '') === $expectedName
        );

        $this->assertNotNull($match, 'Expected ICS attachment named '.$expectedName);

        return $match;
    }
}
