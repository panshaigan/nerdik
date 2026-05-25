<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\User;
use App\Notifications\ActivityCancelledNotification;
use App\Notifications\ActivityReopenedNotification;
use App\Notifications\EventCancelledNotification;
use App\Notifications\EventReopenedNotification;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalRejectedNotification;
use App\Notifications\ProposalSubmittedNotification;
use App\Notifications\WaitlistPromotedNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

use function fake;

/**
 * @extends Factory<DatabaseNotification>
 */
final class DatabaseNotificationFactory extends Factory
{
    protected $model = DatabaseNotification::class;

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'type' => ProposalSubmittedNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => User::factory(),
            'data' => ['type' => 'proposal_submitted'],
            'read_at' => null,
        ];
    }

    public function fromNotification(Notification $notification, User $notifiable): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $notification::class,
            'data' => $notification->toArray($notifiable),
        ]);
    }

    public function unread(): self
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => null,
        ]);
    }

    public function read(): self
    {
        return $this->state(fn (array $attributes): array => [
            'read_at' => now(),
        ]);
    }

    public function createdBetween(Carbon $from, Carbon $to): self
    {
        return $this->state(function (array $attributes) use ($from, $to): array {
            $createdAt = Carbon::instance(fake()->dateTimeBetween($from, $to));

            $state = [
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if ($attributes['read_at'] !== null) {
                $readAt = $createdAt->copy()->addSeconds(fake()->numberBetween(60, 86400));
                if ($readAt->isAfter($to)) {
                    $readAt = $to->copy();
                }
                $state['read_at'] = $readAt;
                $state['updated_at'] = $readAt;
            }

            return $state;
        });
    }

    public function randomSampleNotification(SampleNotificationContext $context, User $notifiable): self
    {
        $notification = $this->buildRandomNotification($context);

        $factory = $this->fromNotification($notification, $notifiable);

        if (fake()->boolean(70)) {
            $factory = $factory->unread();
        } else {
            $factory = $factory->read();
        }

        return $factory->createdBetween(now()->subDays(30), now());
    }

    private function buildRandomNotification(SampleNotificationContext $context): Notification
    {
        $builders = [
            fn (): Notification => new ProposalSubmittedNotification($context->proposals->random()),
            fn (): Notification => new ProposalAcceptedNotification($context->proposals->random()),
            fn (): Notification => new ProposalRejectedNotification($context->proposals->random()),
            fn (): Notification => new WaitlistPromotedNotification($context->activities->random()),
            fn (): Notification => new ActivityCancelledNotification(
                $context->activities->random(),
                $context->users->random(),
            ),
            fn (): Notification => new ActivityReopenedNotification(
                $context->activities->random(),
                $context->users->random(),
            ),
            fn (): Notification => $this->buildEventCancelledNotification($context->events->random()),
            fn (): Notification => new EventReopenedNotification(
                $context->events->random(),
                $context->users->random(),
            ),
        ];

        return fake()->randomElement($builders)();
    }

    private function buildEventCancelledNotification(Event $event): EventCancelledNotification
    {
        return new EventCancelledNotification(
            (int) $event->id,
            $event->name,
            $event->slug ?? '',
        );
    }
}
