<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\Tag;
use App\Models\TagCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ParticipantRosterPdfBuilder
{
    /**
     * @return array{
     *     name: string,
     *     documentTitle: string,
     *     host: string|null,
     *     gameNames: list<string>,
     *     slotName: string|null,
     *     time: string|null,
     *     room: string|null,
     *     participants: list<array{name: string, is_absent: bool}>
     * }
     */
    public function activityRoster(Activity $activity): array
    {
        $this->loadActivityRosterRelations($activity);

        return $this->mapActivityRoster($activity);
    }

    /**
     * @return array{
     *     eventName: string,
     *     documentTitle: string,
     *     place: string|null,
     *     time: string|null,
     *     activities: list<array{
     *         name: string,
     *         documentTitle: string,
     *         host: string|null,
     *         gameNames: list<string>,
     *         slotName: string|null,
     *         time: string|null,
     *         room: string|null,
     *         participants: list<array{name: string, is_absent: bool}>
     *     }>
     * }
     */
    public function eventRoster(Event $event): array
    {
        $event->loadMissing('places.city');

        $activities = Activity::query()
            ->whereNull('cancelled_at')
            ->whereHas('slot', fn ($query) => $query->where('event_id', $event->id))
            ->with([
                'creator.organization',
                'participants' => fn ($query) => $query->whereNull('deleted_at'),
                'participants.user.organization',
                'tags.translations',
                'tags.tagCategory',
                'slot.place.parent',
                'place.parent',
            ])
            ->get()
            ->sortBy(fn (Activity $activity): string => (string) ($activity->slot?->starts_at?->timestamp ?? PHP_INT_MAX).'-'.$activity->id)
            ->values();

        $eventName = (string) $event->name;
        $placeSummary = trim($event->compactPlaceSummary());
        $timeSummary = trim(format_date_range_compact($event->starts_at, $event->ends_at));

        return [
            'eventName' => $eventName,
            'documentTitle' => $this->namedTitle($eventName),
            'place' => $placeSummary !== '' ? $placeSummary : null,
            'time' => $timeSummary !== '' ? $timeSummary : null,
            'activities' => $activities
                ->map(fn (Activity $activity): array => $this->mapActivityRoster($activity))
                ->all(),
        ];
    }

    public function streamForActivity(Activity $activity): Response
    {
        $roster = $this->activityRoster($activity);
        $filename = $this->filenameFromSlug((string) $activity->slug, 'participants');

        return Pdf::loadView('pdf.activity-participants', ['roster' => $roster])
            ->stream($filename);
    }

    public function streamForEvent(Event $event): Response
    {
        $roster = $this->eventRoster($event);
        $filename = $this->filenameFromSlug((string) $event->slug, 'participants');

        return Pdf::loadView('pdf.event-participants', ['roster' => $roster])
            ->stream($filename);
    }

    private function loadActivityRosterRelations(Activity $activity): void
    {
        $activity->load([
            'creator.organization',
            'participants' => fn ($query) => $query->whereNull('deleted_at'),
            'participants.user.organization',
            'tags.translations',
            'tags.tagCategory',
            'slot.place.parent',
            'place.parent',
        ]);
    }

    /**
     * @return array{
     *     name: string,
     *     documentTitle: string,
     *     host: string|null,
     *     gameNames: list<string>,
     *     slotName: string|null,
     *     time: string|null,
     *     room: string|null,
     *     participants: list<array{name: string, is_absent: bool}>
     * }
     */
    private function mapActivityRoster(Activity $activity): array
    {
        /** @var Collection<int, Tag> $gameTags */
        $gameTags = $activity->tags
            ->filter(fn ($tag): bool => $tag->tagCategory?->key === TagCategory::KEY_GAME)
            ->values();

        $participants = $activity->participants
            ->filter(fn ($participant): bool => $participant->deleted_at === null)
            ->map(fn ($participant): array => [
                'name' => $participant->user?->badgeDisplayName() ?? __('ui.common.unknown_user'),
                'is_absent' => (bool) $participant->is_absent,
            ])
            ->sortBy(fn (array $participant): string => Str::lower($participant['name']), SORT_NATURAL)
            ->values()
            ->all();

        $slot = $activity->slot;
        $slotName = $slot !== null ? trim((string) $slot->name) : '';
        $startsAt = $slot?->starts_at ?? $activity->starts_at;
        $endsAt = $slot?->ends_at ?? $activity->ends_at;
        $place = $slot?->place ?? $activity->place;
        $name = (string) $activity->name;

        return [
            'name' => $name,
            'documentTitle' => $this->namedTitle($name),
            'host' => $activity->creator?->badgeDisplayName(),
            'gameNames' => $gameTags
                ->map(fn ($tag): string => $tag->displayLabel())
                ->filter(fn (string $label): bool => $label !== '')
                ->values()
                ->all(),
            'slotName' => $slotName !== '' ? $slotName : null,
            'time' => $this->formatTimeRange($startsAt, $endsAt),
            'room' => $this->roomNameFromPlace($place),
            'participants' => $participants,
        ];
    }

    private function namedTitle(string $name): string
    {
        return __('ui.pdf.roster.title_named', ['name' => $name]);
    }

    private function formatTimeRange(mixed $start, mixed $end): ?string
    {
        if ($start === null && $end === null) {
            return null;
        }

        if ($start !== null && $end !== null) {
            $startDay = format_in_user_tz($start, 'Y-m-d');
            $endDay = format_in_user_tz($end, 'Y-m-d');

            if ($startDay === $endDay) {
                return format_in_user_tz($start, 'd M Y H:i').' – '.format_in_user_tz($end, 'H:i');
            }

            return format_in_user_tz($start, 'd M Y H:i').' – '.format_in_user_tz($end, 'd M Y H:i');
        }

        /** @var CarbonInterface $moment */
        $moment = $start ?? $end;

        return format_in_user_tz($moment, 'd M Y H:i');
    }

    private function roomNameFromPlace(?Place $place): ?string
    {
        if ($place === null) {
            return null;
        }

        $place->loadMissing('parent');

        if ($place->type === Place::TYPE_ROOM || $place->parent_id !== null) {
            $name = trim((string) $place->name);

            return $name !== '' ? $name : null;
        }

        return null;
    }

    private function filenameFromSlug(string $slug, string $suffix): string
    {
        $safe = Str::slug($slug);

        if ($safe === '') {
            $safe = 'roster';
        }

        return $safe.'-'.$suffix.'.pdf';
    }
}
