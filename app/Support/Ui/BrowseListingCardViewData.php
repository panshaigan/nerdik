<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Domain\ActivityBadges\ActivityBadgeItem;

final readonly class BrowseListingCardViewData
{
    /**
     * @param  array<int, ActivityBadgeItem>  $badgeItems
     */
    public function __construct(
        public string $kind,
        public int $id,
        public string $name,
        public ?string $logoUrl,
        public string $detailsUrl,
        public string $editUrl,
        public bool $isOwner,
        public bool $isInterested,
        public string $interestWireMethod,
        public string $timeSummary,
        public string $locationSummary,
        public ?string $hostingCornerLabel,
        public bool $showParticipants,
        public int $participantsFilled,
        public ?int $participantsMax,
        public array $badgeItems,
        public string $cardModifierClass,
        public string $dataUiPrefix,
        public string $badgeGroupDataUi,
        public string $listingKindIcon,
        public string $listingKindTitle,
        public string $editTitle,
        public string $openAriaLabel,
        public string $previewWireMethod,
    ) {}
}
