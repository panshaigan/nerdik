<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Signals that an activity roster (participants/waitlist/absent flags) changed.
 */
class ActivityParticipationUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $activityId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('activity.'.$this->activityId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'activity.participation.updated';
    }

    /**
     * @return array{activityId: int}
     */
    public function broadcastWith(): array
    {
        return [
            'activityId' => $this->activityId,
        ];
    }
}
