<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserPlayArea;
use Illuminate\Validation\ValidationException;

class UserPlayPreferencesService
{
    public const MIN_RADIUS_KM = 1;

    public const MAX_RADIUS_KM = 300;

    public const DEFAULT_RADIUS_KM = 25;

    public function savePlayArea(
        User $user,
        float $latitude,
        float $longitude,
        int $radiusKm,
        ?string $label = null,
        ?int $cityId = null,
    ): UserPlayArea {
        $this->assertValidCoordinates($latitude, $longitude);
        $this->assertValidRadius($radiusKm);

        $normalizedLabel = $label !== null ? trim($label) : null;
        if ($normalizedLabel === '') {
            $normalizedLabel = null;
        }

        return UserPlayArea::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'radius_km' => $radiusKm,
                'label' => $normalizedLabel,
                'city_id' => $cityId,
            ],
        );
    }

    public function clearPlayArea(User $user): void
    {
        UserPlayArea::query()->where('user_id', $user->id)->delete();
    }

    /**
     * @param  array<int|string, mixed>  $tagIds
     * @param  array<int|string, mixed>  $newTags
     */
    public function syncInterestTags(User $user, array $tagIds, array $newTags = []): void
    {
        $tagSelection = app(TagSelectionService::class);

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

            $resolvedIds[] = $tagSelection->findOrCreateTagByLabel($label, $categoryId);
        }

        $resolvedIds = array_values(array_unique($resolvedIds));
        sort($resolvedIds);

        $user->interestTags()->sync($resolvedIds);
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
