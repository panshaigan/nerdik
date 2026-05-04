<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\User;
use App\Models\UserProfile;

/**
 * Notification preference keys stored in {@see UserProfile::$notification_preferences}.
 */
enum NotificationPreferenceKey: string
{
    case Proposals = 'proposals';
    case WaitlistPromoted = 'waitlist_promoted';
    case ActivityParticipantJoined = 'activity_participant_joined';
    case ActivityParticipantLeft = 'activity_participant_left';
    case ActivityRemovedByHost = 'activity_removed_by_host';
    case ActivityCancelled = 'activity_cancelled';
    case ActivityReopened = 'activity_reopened';
    case EventCancelled = 'event_cancelled';
    case EventReopened = 'event_reopened';
    case ScheduledOrganizerUnansweredProposals = 'scheduled_organizer_unanswered_proposals';
    case ScheduledInterestedEnrollmentWindow = 'scheduled_interested_enrollment_window';
    case ScheduledDashboardFeed = 'scheduled_dashboard_feed';
    case ScheduledParticipantCancellationDeadline = 'scheduled_participant_cancellation_deadline';
    case ScheduledHostLowParticipation = 'scheduled_host_low_participation';

    /**
     * @return array<string, array{in_app: bool, email: bool}>
     */
    public static function defaultMatrix(): array
    {
        $matrix = [];
        foreach (self::cases() as $case) {
            $matrix[$case->value] = [
                'in_app' => true,
                'email' => true,
            ];
        }

        return $matrix;
    }

    public static function tryFromScheduledCategory(string $category): ?self
    {
        return match ($category) {
            'organizer_unanswered_proposals' => self::ScheduledOrganizerUnansweredProposals,
            'interested_enrollment_window' => self::ScheduledInterestedEnrollmentWindow,
            'dashboard_feed' => self::ScheduledDashboardFeed,
            'participant_cancellation_deadline' => self::ScheduledParticipantCancellationDeadline,
            'host_low_participation' => self::ScheduledHostLowParticipation,
            default => null,
        };
    }

    /**
     * Grouped preference keys as shown on the profile screen.
     *
     * @return list<array{group_key: string, keys: list<self>}>
     */
    public static function uiSections(): array
    {
        return [
            [
                'group_key' => 'activity_group',
                'keys' => [
                    self::Proposals,
                    self::WaitlistPromoted,
                    self::ActivityParticipantJoined,
                    self::ActivityParticipantLeft,
                    self::ActivityRemovedByHost,
                ],
            ],
            [
                'group_key' => 'event_group',
                'keys' => [
                    self::ActivityCancelled,
                    self::ActivityReopened,
                    self::EventCancelled,
                    self::EventReopened,
                ],
            ],
            [
                'group_key' => 'scheduled_group',
                'keys' => [
                    self::ScheduledOrganizerUnansweredProposals,
                    self::ScheduledInterestedEnrollmentWindow,
                    self::ScheduledDashboardFeed,
                    self::ScheduledParticipantCancellationDeadline,
                    self::ScheduledHostLowParticipation,
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function channelsFor(User $user): array
    {
        $channels = [];
        if ($user->wantsNotificationChannel($this, 'in_app')) {
            $channels[] = 'database';
            $channels[] = 'broadcast';
        }
        if ($user->wantsNotificationChannel($this, 'email')) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
