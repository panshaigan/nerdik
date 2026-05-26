<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Domain\ActivityBadges\ActivityBadgeGroupConfig;
use App\Domain\ActivityBadges\ActivityBadgeItem;
use App\Models\Activity;
use App\Models\Event;

final class BrowseListingCardPresenter
{
    public function __construct(
        private ActivityBadgeGroupBuilder $badgeGroupBuilder,
        private ActivityListingImageResolver $activityListingImageResolver,
        private EventListingImageResolver $eventListingImageResolver,
    ) {}

    /**
     * @param  list<int>  $interestedIds
     */
    public function fromActivity(Activity $activity, array $interestedIds, ?string $returnUrl = null): BrowseListingCardViewData
    {
        $return = safe_return_url($returnUrl) ?? browsing_return_url();
        $currentUser = auth()->user();
        $isOwner = $currentUser !== null && (int) ($activity->created_by ?? 0) === (int) $currentUser->id;
        $parentEvent = (int) ($activity->hosting_mode ?? 0) === Activity::HOSTING_MODE_SCHEDULED_ON_EVENT
            ? $activity->slot?->event
            : null;
        $participantsFilled = isset($activity->participants_count)
            ? (int) $activity->participants_count
            : (int) $activity->participants()->where('is_absent', false)->count();
        $timeSourceStartsAt = $activity->slot?->starts_at ?? $activity->starts_at;
        $timeSourceEndsAt = $activity->slot?->ends_at ?? $activity->ends_at;
        $place = $activity->slot?->place ?? $activity->place;
        $venueName = $place?->venueName() ?? '';

        return new BrowseListingCardViewData(
            kind: 'activity',
            id: (int) $activity->id,
            name: (string) $activity->name,
            coverPicture: $this->activityListingImageResolver->resolve($activity),
            detailsUrl: route('activities.show', $activity),
            editUrl: url_with_return(route('activities.edit', $activity), $return),
            isOwner: $isOwner,
            isInterested: in_array((int) $activity->id, $interestedIds, true),
            interestWireMethod: 'toggleActivityInterest',
            timeSummary: format_date_range_compact($timeSourceStartsAt, $timeSourceEndsAt),
            locationSummary: filled($venueName) ? (string) $venueName : '',
            kindCornerLabel: __('ui.browse.listing_kind_activity'),
            hostUser: $activity->creator,
            hostOrganization: null,
            parentEventName: $parentEvent !== null ? (string) $parentEvent->name : null,
            parentEventUrl: $parentEvent !== null ? route('events.show', $parentEvent) : null,
            showParticipants: true,
            participantsFilled: $participantsFilled,
            participantsMax: $activity->max_participants !== null ? (int) $activity->max_participants : null,
            badgeItems: $this->badgeGroupBuilder->build(
                $activity,
                ActivityBadgeGroupConfig::browseCard(),
            ),
            cardModifierClass: 'ui-card-activity',
            dataUiPrefix: 'activity-card',
            badgeGroupDataUi: 'activity-card-badge-group',
            editTitle: __('ui.activities.edit_activity'),
            openAriaLabel: __('Open activity').': '.$activity->name,
            previewWireMethod: 'openListingActivityPreview',
        );
    }

    /**
     * @param  list<int>  $interestedIds
     */
    public function fromEvent(Event $event, array $interestedIds, ?string $returnUrl = null): BrowseListingCardViewData
    {
        $return = safe_return_url($returnUrl) ?? browsing_return_url();
        $currentUser = auth()->user();
        $isOwner = $currentUser !== null && (int) ($event->created_by ?? 0) === (int) $currentUser->id;

        return new BrowseListingCardViewData(
            kind: 'event',
            id: (int) $event->id,
            name: (string) $event->name,
            coverPicture: $this->eventListingImageResolver->resolve(),
            detailsUrl: route('events.show', $event),
            editUrl: url_with_return(route('events.edit', $event), $return),
            isOwner: $isOwner,
            isInterested: in_array((int) $event->id, $interestedIds, true),
            interestWireMethod: 'toggleEventInterest',
            timeSummary: format_date_range_compact($event->starts_at, $event->ends_at),
            locationSummary: $this->eventLocationSummary($event),
            kindCornerLabel: __('ui.browse.listing_kind_event'),
            hostUser: $event->creator,
            hostOrganization: $event->organization,
            parentEventName: null,
            parentEventUrl: null,
            showParticipants: false,
            participantsFilled: 0,
            participantsMax: null,
            badgeItems: $this->eventSlotTypeBadgeItems($event),
            cardModifierClass: 'ui-card-event',
            dataUiPrefix: 'event-card',
            badgeGroupDataUi: 'event-card-slot-type-badges',
            editTitle: __('ui.events.edit_event'),
            openAriaLabel: __('Open event').': '.$event->name,
            previewWireMethod: 'openListingEventPreview',
        );
    }

    private function eventLocationSummary(Event $event): string
    {
        $venueSummary = $event->compactPlaceSummary();
        if ($venueSummary !== '') {
            return $venueSummary;
        }

        $locale = app()->getLocale();
        $locationLabels = [];
        foreach ($event->places as $place) {
            $city = $place->city?->name($locale);
            $country = $place->country?->name($locale);
            $c = $city ? trim($city) : null;
            $co = $country ? trim($country) : null;
            if ($c !== null && $co !== null && mb_strtolower($c) === mb_strtolower($co)) {
                $label = $c;
            } else {
                $label = implode(', ', array_filter([$c, $co]));
            }
            if ($label !== '') {
                $locationLabels[mb_strtolower($label)] = $label;
            }
        }

        return implode(' · ', array_values($locationLabels));
    }

    /**
     * @return array<int, ActivityBadgeItem>
     */
    private function eventSlotTypeBadgeItems(Event $event): array
    {
        $slotTypeLabels = collect($event->slots ?? [])
            ->flatMap(function ($slot) {
                $activityTypeFromActivity = $slot->activity?->activityType?->slug
                    ? [__('ui.activities.types.'.$slot->activity->activityType->slug)]
                    : [];

                $allowedTypesFromSlot = collect($slot->activityTypes ?? [])
                    ->map(fn ($row) => $row->slug ? __('ui.activities.types.'.$row->slug) : null)
                    ->filter()
                    ->values()
                    ->all();

                return array_merge($activityTypeFromActivity, $allowedTypesFromSlot);
            })
            ->filter()
            ->unique()
            ->values();

        if ($slotTypeLabels->isEmpty()) {
            return [];
        }

        return $this->badgeGroupBuilder->buildActivityTypeChips($slotTypeLabels);
    }
}
