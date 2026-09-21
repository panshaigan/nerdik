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
        public readonly bool $canMarkParticipantsAbsent,
        public readonly ?string $signupBlockedMessage,
        public readonly ?string $stateBlockedMessage,
        public readonly ?int $activeWindowPerActivityMax,
        public readonly ?int $activeWindowRemainingForActivity,
        public readonly ?int $activeWindowUserRemaining,
        public readonly bool $isLotteryPending = false,
        public readonly bool $isLotteryResolved = false,
        /** @var list<array{message: string, dataUi: string}> */
        public readonly array $lotteryDrawNotices = [],
        public readonly bool $canPromptGuestJoin = false,
        public readonly bool $canAnnounceLate = false,
        public readonly bool $canSetOthersLate = false,
        public readonly ?int $viewerLateMinutes = null,
        public readonly ?int $hostLateMinutes = null,
    ) {}
}
