<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Place;
use Illuminate\Database\Seeder;

class PlaceSeeder extends Seeder
{
    /**
     * Sample physical venues only. Country/city names come from {@see Country} and {@see City}, not from place rows.
     */
    public function run(): void
    {
        $country = Country::query()->where('iso_alpha2', 'PL')->first();
        if (! $country) {
            return;
        }

        $warsaw = $this->cityByEnglishName($country->id, 'Warsaw');
        $wroclaw = $this->cityByEnglishName($country->id, 'Wrocław');

        if ($warsaw) {
            Place::firstOrCreate(
                ['slug' => 'sample-venue-warsaw'],
                [
                    'name' => 'Sample RPG space (Warsaw)',
                    'type' => 'venue',
                    'country_id' => $country->id,
                    'city_id' => $warsaw->id,
                    'is_online' => false,
                ]
            );
        }

        if ($wroclaw) {
            Place::firstOrCreate(
                ['slug' => 'sample-venue-wroclaw'],
                [
                    'name' => 'Sample board game café (Wrocław)',
                    'type' => 'venue',
                    'country_id' => $country->id,
                    'city_id' => $wroclaw->id,
                    'is_online' => false,
                ]
            );
        }
    }

    private function cityByEnglishName(int $countryId, string $englishName): ?City
    {
        return City::query()
            ->where('country_id', $countryId)
            ->whereHas('translations', function ($q) use ($englishName) {
                $q->where('locale', 'en')->where('name', $englishName);
            })
            ->first();
    }
}
