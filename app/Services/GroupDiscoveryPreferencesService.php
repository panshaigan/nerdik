<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Persists group location/radius and interest tags used by {@see GroupCandidateDiscoveryService}.
 */
class GroupDiscoveryPreferencesService
{
    public const MIN_RADIUS_KM = UserPlayPreferencesService::MIN_RADIUS_KM;

    public const MAX_RADIUS_KM = UserPlayPreferencesService::MAX_RADIUS_KM;

    public const DEFAULT_RADIUS_KM = UserPlayPreferencesService::DEFAULT_RADIUS_KM;

    public function __construct(
        private readonly TagSelectionService $tagSelection,
    ) {}

    public function saveLocation(
        Group $group,
        User $actor,
        float $latitude,
        float $longitude,
        int $radiusKm,
        ?string $label = null,
        ?int $cityId = null,
    ): Group {
        $this->assertCanManage($group, $actor);
        $this->assertValidCoordinates($latitude, $longitude);
        $this->assertValidRadius($radiusKm);

        $normalizedLabel = $label !== null ? trim($label) : null;
        if ($normalizedLabel === '') {
            $normalizedLabel = null;
        }

        $group->fill([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius_km' => $radiusKm,
            'location_label' => $normalizedLabel,
            'city_id' => $cityId,
        ]);
        $group->save();

        return $group->refresh();
    }

    public function clearLocation(Group $group, User $actor): Group
    {
        $this->assertCanManage($group, $actor);

        $group->fill([
            'latitude' => null,
            'longitude' => null,
            'radius_km' => null,
            'location_label' => null,
            'city_id' => null,
        ]);
        $group->save();

        return $group->refresh();
    }

    /**
     * @param  array<int|string, mixed>  $tagIds
     * @param  array<int|string, mixed>  $newTags
     */
    public function syncTags(Group $group, User $actor, array $tagIds, array $newTags = []): void
    {
        $this->assertCanManage($group, $actor);

        $resolvedIds = collect($tagIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        foreach ($newTags as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $categoryId = (int) ($row['category_id'] ?? 0);

            if ($label === '' || $categoryId <= 0) {
                continue;
            }

            $resolvedIds[] = $this->tagSelection->findOrCreateTagByLabel($label, $categoryId);
        }

        $resolvedIds = array_values(array_unique($resolvedIds));
        sort($resolvedIds);

        $group->tags()->sync($resolvedIds);
    }

    private function assertCanManage(Group $group, User $actor): void
    {
        if (! $actor->canModifyEntity($group)) {
            throw ValidationException::withMessages([
                'group' => [__('ui.groups.manage_unauthorized')],
            ]);
        }
    }

    private function assertValidCoordinates(float $latitude, float $longitude): void
    {
        if ($latitude < -90 || $latitude > 90) {
            throw ValidationException::withMessages([
                'playLatitude' => [__('validation.between.numeric', ['attribute' => 'latitude', 'min' => -90, 'max' => 90])],
            ]);
        }

        if ($longitude < -180 || $longitude > 180) {
            throw ValidationException::withMessages([
                'playLongitude' => [__('validation.between.numeric', ['attribute' => 'longitude', 'min' => -180, 'max' => 180])],
            ]);
        }
    }

    private function assertValidRadius(int $radiusKm): void
    {
        if ($radiusKm < self::MIN_RADIUS_KM || $radiusKm > self::MAX_RADIUS_KM) {
            throw ValidationException::withMessages([
                'playRadiusKm' => [__('validation.between.numeric', [
                    'attribute' => 'radius',
                    'min' => self::MIN_RADIUS_KM,
                    'max' => self::MAX_RADIUS_KM,
                ])],
            ]);
        }
    }
}
