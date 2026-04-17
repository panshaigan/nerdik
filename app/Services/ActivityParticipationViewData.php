<?php

namespace App\Services;

/**
 * Presentation state for activity show: enrollment window quotas, join eligibility, and UI flags.
 */
final class ActivityParticipationViewData
{
    public function __construct(
        public readonly bool $isParticipant,
        public readonly bool $onWaitlist,
        public readonly bool $canJoin,
        public readonly bool $isFull,
        public readonly bool $hasInterest,
        public readonly bool $canManageActivity,
        public readonly ?string $signupBlockedMessage,
        public readonly ?string $stateBlockedMessage,
        public readonly ?int $activeWindowPerActivityMax,
        public readonly ?int $activeWindowRemainingForActivity,
        public readonly ?int $activeWindowUserRemaining,
    ) {}
}
