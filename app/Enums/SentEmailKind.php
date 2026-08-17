<?php

declare(strict_types=1);

namespace App\Enums;

use App\Mail\BackupFailedMail;
use App\Notifications\ActivityCancelledNotification;
use App\Notifications\ActivityParticipantJoinedNotification;
use App\Notifications\ActivityParticipantLeftNotification;
use App\Notifications\ActivityRemovedByHostNotification;
use App\Notifications\ActivityReopenedNotification;
use App\Notifications\EventCancelledNotification;
use App\Notifications\EventReopenedNotification;
use App\Notifications\ProposalAcceptedNotification;
use App\Notifications\ProposalRejectedNotification;
use App\Notifications\ProposalSubmittedNotification;
use App\Notifications\Scheduled\ScheduledPeriodicDigestNotification;
use App\Notifications\UserRequestReceivedNotification;
use App\Notifications\UserRequestResolvedNotification;
use App\Notifications\VerifyPendingEmailNotification;
use App\Notifications\WaitlistPromotedNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Str;

enum SentEmailKind: string
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
    case ScheduledDigest = 'scheduled_digest';
    case UserRequests = 'user_requests';
    case VerifyEmail = 'verify_email';
    case ResetPassword = 'reset_password';
    case VerifyPendingEmail = 'verify_pending_email';
    case BackupFailed = 'backup_failed';
    case Unknown = 'unknown';

    public function label(): string
    {
        return Str::of($this->value)->replace('_', ' ')->headline()->toString();
    }

    public static function fromPreferenceKey(NotificationPreferenceKey $key): self
    {
        return self::from($key->value);
    }

    public static function fromSourceClass(string $class): self
    {
        return match ($class) {
            WaitlistPromotedNotification::class => self::WaitlistPromoted,
            ActivityCancelledNotification::class => self::ActivityCancelled,
            ActivityReopenedNotification::class => self::ActivityReopened,
            EventCancelledNotification::class => self::EventCancelled,
            EventReopenedNotification::class => self::EventReopened,
            ProposalSubmittedNotification::class,
            ProposalAcceptedNotification::class,
            ProposalRejectedNotification::class => self::Proposals,
            ActivityRemovedByHostNotification::class => self::ActivityRemovedByHost,
            ActivityParticipantJoinedNotification::class => self::ActivityParticipantJoined,
            ActivityParticipantLeftNotification::class => self::ActivityParticipantLeft,
            UserRequestReceivedNotification::class,
            UserRequestResolvedNotification::class => self::UserRequests,
            ScheduledPeriodicDigestNotification::class => self::ScheduledDigest,
            VerifyPendingEmailNotification::class => self::VerifyPendingEmail,
            VerifyEmail::class => self::VerifyEmail,
            ResetPassword::class => self::ResetPassword,
            BackupFailedMail::class => self::BackupFailed,
            default => self::Unknown,
        };
    }
}
