<?php

declare(strict_types=1);

use App\Notifications\ActivityCancelledNotification;
use App\Notifications\ActivityParticipantJoinedNotification;
use App\Notifications\ActivityParticipantLeftNotification;
use App\Notifications\ActivityRemovedByHostNotification;
use App\Notifications\ActivityReopenedNotification;
use App\Notifications\EventCancelledNotification;
use App\Notifications\EventReopenedNotification;
use App\Notifications\ProposalSubmittedNotification;
use App\Notifications\WaitlistPromotedNotification;

return [

    'enabled' => env('NOTIFICATION_THROTTLE_ENABLED', true),

    'participation_mutations_per_minute' => (int) env('NOTIFICATION_THROTTLE_PARTICIPATION_PER_MINUTE', 3),

    'lifecycle_mutations_per_minute' => (int) env('NOTIFICATION_THROTTLE_LIFECYCLE_PER_MINUTE', 1),

    'cooldown_seconds' => [
        ActivityParticipantJoinedNotification::class => 15 * 60,
        ActivityParticipantLeftNotification::class => 15 * 60,
        WaitlistPromotedNotification::class => 15 * 60,
        ActivityCancelledNotification::class => 15 * 60,
        ActivityReopenedNotification::class => 15 * 60,
        EventCancelledNotification::class => 15 * 60,
        EventReopenedNotification::class => 15 * 60,
        ActivityRemovedByHostNotification::class => 5 * 60,
        ProposalSubmittedNotification::class => 5 * 60,
    ],

];
