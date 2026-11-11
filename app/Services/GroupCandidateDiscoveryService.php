<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRequestType;
use App\Models\Group;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserPlayArea;
use App\Models\UserRequest;
use App\Support\Geo\BoundingBox;
use App\Support\Geo\Haversine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;

class GroupCandidateDiscoveryService
{
    private const float EARTH_RADIUS_KM = Haversine::EARTH_RADIUS_KM;

    public function __construct(
        private readonly TagSelectionService $tagSelection,
    ) {}

    public function hasDiscoveryCriteria(Group $group): bool
    {
        $group->loadMissing('tags');

        return $group->tags->isNotEmpty() || $group->hasLocation();
    }

    public function emptyCandidates(int $perPage = 20): LengthAwarePaginator
    {
        return $this->emptyPage($perPage);
    }

    public function candidates(Group $group, User $actor, int $perPage = 20): LengthAwarePaginator
    {
        if (! $actor->canModifyEntity($group)) {
            return $this->emptyPage($perPage);
        }

        $group->loadMissing('tags');

        $hasTags = $group->tags->isNotEmpty();
        $hasLocation = $group->hasLocation();

        if (! $hasTags && ! $hasLocation) {
            return $this->emptyPage($perPage);
        }

        $excludedIds = $group->users()->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->push((int) $actor->id)
            ->unique()
            ->values()
            ->all();

        $query = User::query()
            ->where('is_deleted', false)
            ->when($excludedIds !== [], fn (Builder $q) => $q->whereNotIn('id', $excludedIds));

        $expandedTagIds = [];
        if ($hasTags) {
            $tagIds = $group->tags->pluck('id')->map(fn ($id) => (int) $id)->all();
            $expandedTagIds = $this->tagSelection->expandTagIdsToDescendants($tagIds);

            if ($expandedTagIds === []) {
                return $this->emptyPage($perPage);
            }

            $query->whereHas('interestTags', fn (Builder $q) => $q->whereIn('tags.id', $expandedTagIds));
        }

        if ($hasLocation) {
            $this->applyLocationOverlap($query, $group);
        }

        $paginator = $query
            ->with(['playArea', 'interestTags.translations'])
            ->orderBy('nickname')
            ->paginate($perPage);

        $pendingRecipientIds = $this->pendingInviteRecipientIds($group, $actor);
        $locale = app()->getLocale();

        $paginator->getCollection()->transform(function (User $user) use (
            $pendingRecipientIds,
            $group,
            $hasTags,
            $hasLocation,
            $expandedTagIds,
            $locale,
        ): User {
            $user->setAttribute(
                'has_pending_group_invite',
                in_array((int) $user->id, $pendingRecipientIds, true),
            );
            $user->setAttribute(
                'discovery_match_tag_labels',
                $hasTags
                    ? $this->matchingInterestTagLabels($user, $expandedTagIds, $locale)
                    : [],
            );
            $user->setAttribute(
                'discovery_match_play_area_caption',
                $hasLocation
                    ? $this->matchingPlayAreaCaption($group, $user->playArea)
                    : null,
            );

            return $user;
        });

        return $paginator;
    }

    /**
     * @param  list<int>  $expandedGroupTagIds
     * @return list<string>
     */
    public function matchingInterestTagLabels(User $candidate, array $expandedGroupTagIds, ?string $locale = null): array
    {
        if ($expandedGroupTagIds === []) {
            return [];
        }

        $locale ??= app()->getLocale();
        $expandedSet = array_fill_keys($expandedGroupTagIds, true);

        return $candidate->interestTags
            ->filter(fn (Tag $tag): bool => isset($expandedSet[(int) $tag->id]))
            ->map(fn (Tag $tag): string => (string) $tag->displayLabel($locale))
            ->filter()
            ->values()
            ->all();
    }

    public function matchingPlayAreaCaption(Group $group, ?UserPlayArea $playArea): ?string
    {
        if (! $group->hasLocation() || $playArea === null) {
            return null;
        }

        $overlaps = Haversine::circlesOverlapKm(
            (float) $group->latitude,
            (float) $group->longitude,
            (int) $group->radius_km,
            (float) $playArea->latitude,
            (float) $playArea->longitude,
            (int) $playArea->radius_km,
        );

        if (! $overlaps) {
            return null;
        }

        $radius = (int) $playArea->radius_km;
        $label = filled($playArea->label) ? (string) $playArea->label : null;

        if ($label !== null) {
            return __('ui.profile.availability.play_area_preview_caption', [
                'label' => $label,
                'radius' => $radius,
            ]);
        }

        return __('ui.profile.availability.play_area_preview_caption_no_label', [
            'radius' => $radius,
        ]);
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyLocationOverlap(Builder $query, Group $group): void
    {
        $groupLat = (float) $group->latitude;
        $groupLng = (float) $group->longitude;
        $groupRadius = (int) $group->radius_km;
        $bboxRadius = $groupRadius + UserPlayPreferencesService::MAX_RADIUS_KM;
        $bbox = BoundingBox::fromCenterRadiusKm($groupLat, $groupLng, $bboxRadius);

        $query->whereHas('playArea', function (Builder $area) use ($groupLat, $groupLng, $groupRadius, $bbox): void {
            $area->whereBetween('latitude', [(float) $bbox['min_lat'], (float) $bbox['max_lat']])
                ->whereBetween('longitude', [(float) $bbox['min_lng'], (float) $bbox['max_lng']])
                ->whereRaw(
                    '(? * acos(least(1.0, greatest(-1.0, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))))) <= ? + radius_km',
                    [self::EARTH_RADIUS_KM, $groupLat, $groupLng, $groupLat, $groupRadius],
                );
        });
    }

    /**
     * @return list<int>
     */
    private function pendingInviteRecipientIds(Group $group, User $actor): array
    {
        return UserRequest::query()
            ->pending()
            ->where('type', UserRequestType::GroupInvite)
            ->where('requester_id', $actor->id)
            ->where('subject_type', $group->getMorphClass())
            ->where('subject_id', $group->getKey())
            ->pluck('recipient_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function emptyPage(int $perPage): LengthAwarePaginator
    {
        return new Paginator([], 0, $perPage);
    }
}
