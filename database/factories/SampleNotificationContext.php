<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\User;
use App\Notifications;
use Illuminate\Support\Collection;

/**
 * Seeded entities used to build real {@see Notifications} instances for sample data.
 *
 * @phpstan-type ProposalCollection Collection<int, ActivityProposal>
 * @phpstan-type ActivityCollection Collection<int, Activity>
 * @phpstan-type EventCollection Collection<int, Event>
 * @phpstan-type UserCollection Collection<int, User>
 */
final readonly class SampleNotificationContext
{
    /**
     * @param  ProposalCollection  $proposals
     * @param  ActivityCollection  $activities
     * @param  EventCollection  $events
     * @param  UserCollection  $users
     */
    public function __construct(
        public Collection $proposals,
        public Collection $activities,
        public Collection $events,
        public Collection $users,
    ) {}
}
