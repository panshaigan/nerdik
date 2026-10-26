<?php

namespace Tests\Unit\Services;

use App\Enums\ActivityProposalStatus;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Services\ActivityHostingModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityHostingModesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function self_hosted_to_proposed_clears_schedule_and_place(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'place_id' => Place::create(['name' => 'Room A', 'type' => 'venue', 'is_online' => false])->id,
        ]);

        app(ActivityHostingModeService::class)->moveSelfHostedToProposed($activity);
        $activity->refresh();

        $this->assertSame(Activity::HOSTING_MODE_PROPOSED_TO_EVENT, $activity->hosting_mode);
        $this->assertNull($activity->place_id);
        $this->assertNull($activity->starts_at);
        $this->assertNull($activity->ends_at);
    }

    #[Test]
    public function self_hosted_to_proposed_is_blocked_with_participants(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
        ]);

        $this->expectException(ValidationException::class);
        app(ActivityHostingModeService::class)->moveSelfHostedToProposed($activity);
    }

    #[Test]
    public function set_draft_clears_schedule_and_place(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'place_id' => Place::create(['name' => 'Room B', 'type' => 'venue', 'is_online' => false])->id,
            'duration_in_minutes' => 90,
        ]);

        app(ActivityHostingModeService::class)->setDraft($activity);
        $activity->refresh();

        $this->assertSame(Activity::HOSTING_MODE_DRAFT, $activity->hosting_mode);
        $this->assertNull($activity->place_id);
        $this->assertNull($activity->starts_at);
        $this->assertNull($activity->ends_at);
    }

    #[Test]
    public function set_self_hosted_assigns_place_and_derives_ends_at(): void
    {
        $place = Place::create(['name' => 'Room C', 'type' => 'venue', 'is_online' => false]);
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'duration_in_minutes' => 120,
            'place_id' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]);

        $startsAt = now()->addDays(2)->startOfHour()->format('Y-m-d\TH:i');

        app(ActivityHostingModeService::class)->setSelfHosted($activity, $place->id, $startsAt);
        $activity->refresh();

        $this->assertSame(Activity::HOSTING_MODE_SELF_HOSTED, $activity->hosting_mode);
        $this->assertSame($place->id, (int) $activity->place_id);
        $this->assertNotNull($activity->starts_at);
        $this->assertNotNull($activity->ends_at);
        $this->assertSame(120, (int) $activity->starts_at->diffInMinutes($activity->ends_at));
    }

    #[Test]
    public function set_self_hosted_rejects_missing_place(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        try {
            app(ActivityHostingModeService::class)->setSelfHosted(
                $activity,
                999_999,
                now()->addDay()->format('Y-m-d\TH:i'),
            );
            $this->fail('Expected ValidationException for missing place.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('self_hosted_place_id', $e->errors());
        }
    }

    #[Test]
    public function set_self_hosted_rejects_invalid_datetime(): void
    {
        $place = Place::create(['name' => 'Room D', 'type' => 'venue', 'is_online' => false]);
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        try {
            app(ActivityHostingModeService::class)->setSelfHosted($activity, $place->id, '');
            $this->fail('Expected ValidationException for empty datetime.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('self_hosted_starts_at', $e->errors());
        }
    }

    #[Test]
    public function scheduled_on_event_locks_draft_proposed_and_self_hosted_move(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        $service = app(ActivityHostingModeService::class);

        foreach ([
            fn () => $service->setDraft($activity),
            fn () => $service->moveSelfHostedToProposed($activity),
            fn () => $service->markProposedToEvent($activity),
        ] as $action) {
            try {
                $action();
                $this->fail('Expected ValidationException when hosting mode is scheduled on event.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('hosting_mode', $e->errors());
                $this->assertSame(
                    __('ui.activities.hosting_mode_locked_scheduled'),
                    $e->errors()['hosting_mode'][0],
                );
            }
        }
    }

    #[Test]
    public function detach_accepted_slot_reopens_proposal_and_sets_proposed_mode(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create([
            'created_by' => $owner->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            'place_id' => null,
            'starts_at' => null,
            'ends_at' => null,
        ]);
        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);
        $proposal = ActivityProposal::factory()->create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => $owner->id,
            'status' => ActivityProposalStatus::Accepted,
            'accepted_slot_id' => $slot->id,
        ]);

        $detached = app(ActivityHostingModeService::class)->detachAcceptedSlot($event, $slot);

        $this->assertTrue($detached);
        $slot->refresh();
        $proposal->refresh();
        $activity->refresh();

        $this->assertNull($slot->activity_id);
        $this->assertSame(ActivityProposalStatus::Pending, $proposal->status);
        $this->assertNull($proposal->accepted_slot_id);
        $this->assertSame(Activity::HOSTING_MODE_PROPOSED_TO_EVENT, $activity->hosting_mode);
    }

    #[Test]
    public function detach_accepted_slot_returns_false_when_slot_has_no_activity(): void
    {
        $event = Event::factory()->create();
        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => null,
        ]);

        $this->assertFalse(app(ActivityHostingModeService::class)->detachAcceptedSlot($event, $slot));
    }

    #[Test]
    public function cancel_performs_once_and_skips_when_already_cancelled(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);
        $service = app(ActivityHostingModeService::class);

        $first = $service->cancel($activity, $host, 'Unavailable');
        $second = $service->cancel($activity->fresh(), $host, 'Again');

        $this->assertTrue($first->performed);
        $this->assertFalse($second->performed);
        $this->assertNotNull($activity->fresh()->cancelled_at);
        $this->assertSame('Unavailable', $activity->fresh()->cancel_reason);
    }

    #[Test]
    public function reopen_performs_once_and_skips_when_not_cancelled(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'cancelled_at' => now(),
            'cancelled_by' => $host->id,
            'cancel_reason' => 'Unavailable',
        ]);
        $service = app(ActivityHostingModeService::class);

        $first = $service->reopen($activity, $host);
        $second = $service->reopen($activity->fresh(), $host);

        $this->assertTrue($first->performed);
        $this->assertFalse($second->performed);
        $this->assertNull($activity->fresh()->cancelled_at);
        $this->assertNull($activity->fresh()->cancel_reason);
    }

    #[Test]
    public function attached_scope_includes_self_hosted_and_public_scheduled_only(): void
    {
        $selfHosted = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
        ]);
        $selfHostedCancelled = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'cancelled_at' => now(),
        ]);
        $draft = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        $publicEvent = Event::factory()->create(['is_public' => true]);
        $privateEvent = Event::factory()->create(['is_public' => false]);
        $scheduledPublic = Activity::factory()->create(['hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT]);
        $scheduledPrivate = Activity::factory()->create(['hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT]);
        Slot::factory()->create(['event_id' => $publicEvent->id, 'activity_id' => $scheduledPublic->id]);
        Slot::factory()->create(['event_id' => $privateEvent->id, 'activity_id' => $scheduledPrivate->id]);

        $ids = Activity::query()->attachedToPublicEvent()->pluck('id')->all();

        $this->assertContains($selfHosted->id, $ids);
        $this->assertContains($scheduledPublic->id, $ids);
        $this->assertNotContains($selfHostedCancelled->id, $ids);
        $this->assertNotContains($draft->id, $ids);
        $this->assertNotContains($scheduledPrivate->id, $ids);
    }

    #[Test]
    public function is_publicly_showable_matches_attached_scope(): void
    {
        $selfHosted = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
        ]);
        $draft = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);
        $proposed = Activity::factory()->proposed()->create();

        $publicEvent = Event::factory()->create(['is_public' => true]);
        $scheduledPublic = Activity::factory()->create(['hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT]);
        Slot::factory()->create(['event_id' => $publicEvent->id, 'activity_id' => $scheduledPublic->id]);

        $this->assertTrue($selfHosted->isPubliclyShowable());
        $this->assertTrue($scheduledPublic->isPubliclyShowable());
        $this->assertFalse($draft->isPubliclyShowable());
        $this->assertFalse($proposed->isPubliclyShowable());
    }
}
