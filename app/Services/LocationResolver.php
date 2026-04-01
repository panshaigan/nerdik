<?php

namespace App\Services;

use App\Models\City;
use App\Models\Country;
use Illuminate\Support\Facades\DB;

class LocationResolver
{
    /**
     * Resolve country + city from Nominatim address payload (expects addressdetails).
     *
     * @param  array<string, mixed>  $address
     * @return array{country_id: int|null, city_id: int|null}
     */
    public function resolveFromNominatimAddress(array $address, ?string $cityName): array
    {
        $iso = isset($address['country_code']) ? strtoupper((string) $address['country_code']) : null;
        $country = $iso ? Country::query()->where('iso_alpha2', $iso)->first() : null;
        $countryId = $country?->id;

        $cityId = null;
        if ($countryId && $cityName && trim($cityName) !== '') {
            $cityId = $this->findOrCreateCityId($countryId, trim($cityName));
        }

        return [
            'country_id' => $countryId,
            'city_id' => $cityId,
        ];
    }

    /**
     * Used when the form sends text and/or partial IDs (legacy or manual edits).
     *
     * @param  array<string, mixed>  $row
     * @return array{country_id: int|null, city_id: int|null}
     */
    public function resolvePlaceRow(array $row): array
    {
        $countryId = $this->intOrNull($row['country_id'] ?? null);
        $cityId = $this->intOrNull($row['city_id'] ?? null);
        $cityStr = isset($row['city']) ? trim((string) $row['city']) : '';
        $countryStr = isset($row['country']) ? trim((string) $row['country']) : '';

        if ($cityId) {
            $city = City::query()->find($cityId);
            if ($city) {
                if ($countryId && (int) $city->country_id !== $countryId) {
                    $countryId = (int) $city->country_id;
                } elseif (! $countryId) {
                    $countryId = (int) $city->country_id;
                }

                return ['country_id' => $countryId, 'city_id' => $cityId];
            }
            $cityId = null;
        }

        if (! $countryId && $countryStr !== '') {
            $countryId = $this->countryIdFromFreeText($countryStr);
        }

        if ($countryId && $cityStr !== '' && ! $cityId) {
            $cityId = $this->findOrCreateCityId($countryId, $cityStr);
        }

        return [
            'country_id' => $countryId,
            'city_id' => $cityId,
        ];
    }

    public function findOrCreateCityId(int $countryId, string $cityName): ?int
    {
        $trim = trim($cityName);
        if ($trim === '') {
            return null;
        }

        $locale = app()->getLocale();
        $lower = mb_strtolower($trim);

        $existing = City::query()
            ->where('country_id', $countryId)
            ->whereHas('translations', function ($q) use ($lower) {
                $q->whereRaw('LOWER(name) = ?', [$lower]);
            })
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return DB::transaction(function () use ($countryId, $trim, $locale) {
            $city = City::create(['country_id' => $countryId]);
            $city->translations()->create(['locale' => $locale, 'name' => $trim]);
            if ($locale !== 'en') {
                $city->translations()->firstOrCreate(
                    ['locale' => 'en'],
                    ['name' => $trim]
                );
            }

            return $city->id;
        });
    }

    private function countryIdFromFreeText(string $text): ?int
    {
        $lower = mb_strtolower(trim($text));
        if ($lower === '') {
            return null;
        }

        if (preg_match('/^[a-z]{2}$/i', $text)) {
            $id = Country::query()->where('iso_alpha2', strtoupper($text))->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        $id = DB::table('country_translations')
            ->whereRaw('LOWER(name) = ?', [$lower])
            ->value('country_id');

        return $id ? (int) $id : null;
    }

    private function intOrNull(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        $i = (int) $v;

        return $i > 0 ? $i : null;
    }
}
