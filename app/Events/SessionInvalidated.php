<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SessionInvalidated implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public int $userId) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("App.Models.User.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'SessionInvalidated';
    }
}
