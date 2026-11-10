<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\ActivityProposalStatus;
use App\Enums\ParticipationMode;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityWaitlistEntry;
use App\Models\User;
use App\Models\UserRequest;
use App\Services\ActivityLotteryService;
use App\Services\UserRequests\UserRequestSubjectLabelResolver;
use Illuminate\Support\Collection;

class DashboardActionQueueService
{
    private const int MAX_ITEMS = 6;

    private const int SOURCE_LIMIT = 3;

    private const int LOTTERY_DAYS_AHEAD = 7;

    public function __construct(
        private readonly UpcomingFeedQueryService $upcomingFeedQuery,
        private readonly UserRequestSubjectLabelResolver $requestLabels,
        private readonly ActivityLotteryService $lotteryService,
        private readonly DashboardHostChoreService $hostChores,
    ) {}

    /**
     * @return list<DashboardActionItem>
     */
    public function itemsFor(User $user): array
    {
        $items = collect()
            ->concat($this->incomingRequests($user))
            ->concat($this->organizerPendingProposals($user))
            ->concat($this->myPendingProposals($user))
            ->concat($this->hostWaitlistApprovals($user))
            ->concat($this->userWaitlistEntries($user))
            ->concat($this->upcomingLotteryDraws($user))
            ->concat($this->hostChores->choresFor($user));

        return $items->take(self::MAX_ITEMS)->values()->all();
    }

    /**
     * @return Collection<int, DashboardActionItem>
     */
    private function incomingRequests(User $user): Collection
    {
        return UserRequest::query()
            ->pendingIncomingFor($user)
            ->with(['requester', 'subject'])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (UserRequest $request): DashboardActionItem {
                $subject = $this->requestLabels->resolve($request);

                return new DashboardActionItem(
                    kind: 'incoming_request',
                    title: $request->type->label(),
                    url: route('requests.index', ['request' => $request->id]),
                    meta: $subject,
                    icon: 'o-inbox-arrow-down',
                );
            });
    }

    /**
     * @return Collection<int, DashboardActionItem>
     */
    private function organizerPendingProposals(User $user): Collection
    {
        return ActivityProposal::query()
            ->where('status', ActivityProposalStatus::Pending)
            ->whereHas('event', fn ($query) => $query->managedByUser($user->id))
            ->with(['activity', 'event'])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (ActivityProposal $proposal): DashboardActionItem {
                $event = $proposal->event;
                $activityName = (string) ($proposal->activity?->name ?? '');

                return new DashboardActionItem(
                    kind: 'proposal_to_review',
                    title: __('ui.dashboard.actions.proposal_to_review'),
                    url: $event !== null
                        ? route('events.show', ['event' => $event, 'tab' => 'proposals'])
                        : route('activity-proposals.index'),
                    meta: $activityName,
                    icon: 'o-clipboard-document-list',
                );
            });
    }

    /**
     * @return Collection<int, DashboardActionItem>
     */
    private function myPendingProposals(User $user): Collection
    {
        return ActivityProposal::query()
            ->where('created_by', $user->id)
            ->where('status', ActivityProposalStatus::Pending)
            ->with(['activity', 'event'])
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (ActivityProposal $proposal): DashboardActionItem {
                $eventName = (string) ($proposal->event?->name ?? '');

                return new DashboardActionItem(
                    kind: 'my_pending_proposal',
                    title: __('ui.dashboard.actions.my_pending_proposal'),
                    url: route('activity-proposals.index'),
                    meta: $eventName !== '' ? $eventName : (string) ($proposal->activity?->name ?? ''),
                    icon: 'o-clock',
                );
            });
    }

    /**
     * @return Collection<int, DashboardActionItem>
     */
    private function hostWaitlistApprovals(User $user): Collection
    {
        return Activity::query()
            ->where('created_by', $user->id)
            ->where('participation_mode', ParticipationMode::HostApproval)
            ->whereNull('cancelled_at')
            ->whereHas('waitlist')
            ->tap(fn ($query) => $this->upcomingFeedQuery->constrainToUpcomingActivities($query))
            ->orderBy('name')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(fn (Activity $activity): DashboardActionItem => new DashboardActionItem(
                kind: 'host_waitlist',
                title: __('ui.dashboard.actions.host_waitlist'),
                url: route('activities.show', ['activity' => $activity]),
                meta: (string) $activity->name,
                icon: 'o-queue-list',
            ));
    }

    /**
     * @return Collection<int, DashboardActionItem>
     */
    private function userWaitlistEntries(User $user): Collection
    {
        return ActivityWaitlistEntry::query()
            ->where('user_id', $user->id)
            ->whereHas('activity', function ($query): void {
                $query->whereNull('cancelled_at');
                $this->upcomingFeedQuery->constrainToUpcomingActivities($query);
            })
            ->with('activity')
            ->orderByDesc('created_at')
            ->limit(self::SOURCE_LIMIT)
            ->get()
            ->map(function (ActivityWaitlistEntry $entry): DashboardActionItem {
                $activity = $entry->activity;

                return new DashboardActionItem(
                    kind: 'user_waitlist',
                    title: __('ui.dashboard.actions.user_waitlist'),
                    url: route('activities.show', ['activity' => $activity]),
                    meta: (string) ($activity?->name ?? ''),
                    icon: 'o-clock',
                );
            });
    }

    /**
     * @return Collection<int, DashboardActionItem>
     */
    private function upcomingLotteryDraws(User $user): Collection
    {
        $hostedIds = Activity::query()
            ->where('created_by', $user->id)
            ->where('participation_mode', ParticipationMode::Lottery)
            ->whereNull('cancelled_at')
            ->tap(fn ($query) => $this->upcomingFeedQuery->constrainToUpcomingActivities($query))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $waitlistIds = ActivityWaitlistEntry::query()
            ->where('user_id', $user->id)
            ->pluck('activity_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $candidateIds = array_values(array_unique([...$hostedIds, ...$waitlistIds]));
        if ($candidateIds === []) {
            return collect();
        }

        $cutoff = now()->addDays(self::LOTTERY_DAYS_AHEAD);

        return Activity::query()
            ->whereIn('id', $candidateIds)
            ->where('participation_mode', ParticipationMode::Lottery)
            ->whereNull('lottery_resolved_at')
            ->whereNull('cancelled_at')
            ->get()
            ->map(function (Activity $activity) use ($cutoff): ?DashboardActionItem {
                $nextDraw = $this->lotteryService->scheduledDraws($activity)->first();
                if ($nextDraw === null || $nextDraw->at->greaterThan($cutoff)) {
                    return null;
                }

                return new DashboardActionItem(
                    kind: 'lottery_draw',
                    title: __('ui.dashboard.actions.lottery_draw'),
                    url: route('activities.show', ['activity' => $activity]),
                    meta: __('ui.dashboard.actions.lottery_draw_meta', [
                        'activity' => (string) $activity->name,
                        'when' => format_datetime_in_user_tz($nextDraw->at),
                    ]),
                    icon: 'o-ticket',
                );
            })
            ->filter()
            ->take(self::SOURCE_LIMIT)
            ->values();
    }
}
