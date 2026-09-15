<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Tag;
use App\Models\TagCategory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ParticipantRosterPdfBuilder
{
    /**
     * @return array{
     *     name: string,
     *     host: string|null,
     *     gameNames: list<string>,
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
     *     activities: list<array{
     *         name: string,
     *         host: string|null,
     *         gameNames: list<string>,
     *         participants: list<array{name: string, is_absent: bool}>
     *     }>
     * }
     */
    public function eventRoster(Event $event): array
    {
        $activities = Activity::query()
            ->whereNull('cancelled_at')
            ->whereHas('slot', fn ($query) => $query->where('event_id', $event->id))
            ->with([
                'creator.organization',
                'participants' => fn ($query) => $query->whereNull('deleted_at')->orderBy('id'),
                'participants.user.organization',
                'tags.translations',
                'tags.tagCategory',
                'slot',
            ])
            ->get()
            ->sortBy(fn (Activity $activity): string => (string) ($activity->slot?->starts_at?->timestamp ?? PHP_INT_MAX).'-'.$activity->id)
            ->values();

        return [
            'eventName' => (string) $event->name,
            'activities' => $activities
                ->map(fn (Activity $activity): array => $this->mapActivityRoster($activity))
                ->all(),
        ];
    }

    public function downloadForActivity(Activity $activity): Response
    {
        $roster = $this->activityRoster($activity);
        $filename = $this->filenameFromSlug((string) $activity->slug, 'participants');

        return Pdf::loadView('pdf.activity-participants', ['roster' => $roster])
            ->download($filename);
    }

    public function downloadForEvent(Event $event): Response
    {
        $roster = $this->eventRoster($event);
        $filename = $this->filenameFromSlug((string) $event->slug, 'participants');

        return Pdf::loadView('pdf.event-participants', ['roster' => $roster])
            ->download($filename);
    }

    private function loadActivityRosterRelations(Activity $activity): void
    {
        $activity->load([
            'creator.organization',
            'participants' => fn ($query) => $query->whereNull('deleted_at')->orderBy('id'),
            'participants.user.organization',
            'tags.translations',
            'tags.tagCategory',
        ]);
    }

    /**
     * @return array{
     *     name: string,
     *     host: string|null,
     *     gameNames: list<string>,
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
            ->values()
            ->all();

        return [
            'name' => (string) $activity->name,
            'host' => $activity->creator?->badgeDisplayName(),
            'gameNames' => $gameTags
                ->map(fn ($tag): string => $tag->displayLabel())
                ->filter(fn (string $label): bool => $label !== '')
                ->values()
                ->all(),
            'participants' => $participants,
        ];
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
