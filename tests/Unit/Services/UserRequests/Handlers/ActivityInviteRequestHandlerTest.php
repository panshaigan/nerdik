<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests\Handlers;

use App\Enums\ParticipationMode;
use App\Enums\UserRequestResolutionOutcome;
use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\ActivityWaitlistEntry;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Slot;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\UserHostBanService;
use App\Services\UserRequests\Handlers\ActivityInviteRequestHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ActivityInviteRequestHandlerTest extends TestCase
{
    use RefreshDatabase;

    private ActivityInviteRequestHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = app(ActivityInviteRequestHandler::class);
    }

    #[Test]
    public function type_is_activity_invite(): void
    {
        $this->assertSame(UserRequestType::ActivityInvite, $this->handler->type());
    }

    #[Test]
    public function assert_can_respond_requires_recipient(): void
    {
        $recipient = User::factory()->create();
        $otherUser = User::factory()->create();
        $request = UserRequest::factory()->activityInvite()->create([
            'recipient_id' => $recipient->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->handler->assertCanRespond($request, $otherUser);
    }

    #[Test]
    public function assert_can_send_rejects_invalid_subject(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($host, $recipient, Organization::factory()->create()),
            'subject',
        );
    }

    #[Test]
    public function assert_can_send_requires_recipient_and_rejects_self(): void
    {
        $host = User::factory()->create();
        $activity = $this->selfHostedActivity($host);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($host, null, $activity),
            'recipient',
        );

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($host, $host, $activity),
            'recipient',
        );
    }

    #[Test]
    public function assert_can_send_rejects_unauthorized_inviter(): void
    {
        $host = User::factory()->create();
        $stranger = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = $this->selfHostedActivity($host);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($stranger, $recipient, $activity),
            'subject',
        );
    }

    #[Test]
    public function assert_can_send_rejects_cancelled_or_draft_activity(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $cancelled = $this->selfHostedActivity($host, ['cancelled_at' => now()]);
        $draft = Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($host, $recipient, $cancelled),
            'subject',
        );
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($host, $recipient, $draft),
            'subject',
        );
    }

    #[Test]
    public function assert_can_send_rejects_cancelled_parent_event(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'cancelled_at' => now(),
        ]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 5,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($host, $recipient, $activity->fresh()->load('slot.event')),
            'subject',
        );
    }

    #[Test]
    public function assert_can_send_rejects_banned_already_participating_or_waitlisted(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = $this->selfHostedActivity($host);

        app(UserHostBanService::class)->ban($host, $recipient);
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($host, $recipient, $activity),
            'recipient',
        );

        $okRecipient = User::factory()->create();
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $okRecipient->id,
            'is_absent' => false,
        ]);
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($host, $okRecipient, $activity),
            'recipient',
        );

        $waitlisted = User::factory()->create();
        $approval = $this->selfHostedActivity($host, [
            'participation_mode' => ParticipationMode::HostApproval,
        ]);
        ActivityWaitlistEntry::query()->create([
            'activity_id' => $approval->id,
            'user_id' => $waitlisted->id,
            'position' => 1,
        ]);
        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($host, $waitlisted, $approval),
            'recipient',
        );
    }

    #[Test]
    public function assert_can_send_rejects_activity_that_already_started(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = $this->selfHostedActivity($host, [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $this->expectValidationKey(
            fn () => $this->handler->assertCanSend($host, $recipient, $activity),
            'subject',
        );
    }

    #[Test]
    public function assert_can_send_allows_valid_invite(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create();
        $activity = $this->selfHostedActivity($host);

        $this->handler->assertCanSend($host, $recipient, $activity);
        $this->assertTrue(true);
    }

    #[Test]
    public function apply_joins_open_activity_invitee(): void
    {
        $host = User::factory()->create();
        $recipient = User::factory()->create(['email_verified_at' => now()]);
        $activity = $this->selfHostedActivity($host);
        $request = UserRequest::factory()->activityInvite()->create([
            'requester_id' => $host->id,
            'recipient_id' => $recipient->id,
            'subject_type' => 'activity',
            'subject_id' => $activity->id,
        ]);
        $request->load(['subject', 'recipient']);

        $outcome = $this->handler->apply($request);

        $this->assertSame(UserRequestResolutionOutcome::Joined, $outcome);
        $this->assertTrue(
            ActivityUser::query()
                ->where('activity_id', $activity->id)
                ->where('user_id', $recipient->id)
                ->exists()
        );
    }

    #[Test]
    public function apply_rejects_invalid_subject_or_recipient(): void
    {
        $request = UserRequest::factory()->activityInvite()->create([
            'recipient_id' => null,
        ]);
        $request->setRelation('subject', Organization::factory()->create());
        $request->setRelation('recipient', null);

        $this->expectValidationKey(
            fn () => $this->handler->apply($request),
            'request',
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function selfHostedActivity(User $host, array $overrides = []): Activity
    {
        return Activity::factory()->create(array_merge([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'participation_mode' => ParticipationMode::Open,
            'max_participants' => 5,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
        ], $overrides));
    }

    private function expectValidationKey(callable $callback, string $key): void
    {
        try {
            $callback();
            $this->fail("Expected ValidationException with key [{$key}].");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($key, $e->errors());
        }
    }
}
