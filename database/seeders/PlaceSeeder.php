<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Place;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sample place seeder
 */
class PlaceSeeder extends Seeder
{
    /**
     * Sample physical venues only. Country/city names come from {@see Country} and {@see City}, not from place rows.
     */
    public function run(array $dataset): void
    {
        $this->seedPolishCities();

        $venues = Place::factory($dataset['places'])
            ->poland()
            ->venue()
            ->predefined()
            ->create();

        foreach ($venues as $venue) {
            Place::factory(fake()->numberBetween(0, $dataset['maxRoomsPerVenue']))
                ->poland()
                ->room($venue)
                ->sequence(
                    ['name' => 'Room A'],
                    ['name' => 'Room B'],
                    ['name' => 'Room C'],
                    ['name' => 'Room D'],
                    ['name' => 'Room E'],
                    ['name' => 'Room F'],
                )
                ->create();
        }
    }

    private function seedPolishCities(): void
    {
        $plId = DB::table('countries')->where('iso_alpha2', 'PL')->value('id');
        if (! $plId) {
            return;
        }

        $cities = [
            ['pl' => 'Warszawa', 'en' => 'Warsaw'],
            ['pl' => 'Kraków'],
            ['pl' => 'Wrocław'],
            ['pl' => 'Poznań'],
            ['pl' => 'Gdańsk'],
            ['pl' => 'Lublin'],
            ['pl' => 'Katowice'],
            ['pl' => 'Białystok'],
        ];

        foreach ($cities as $names) {
            $cityId = DB::table('cities')->insertGetId([
                'country_id' => $plId,
                'slug' => Str::slug($names['pl']),
            ]);
            DB::table('city_translations')->insert([
                ['city_id' => $cityId, 'locale' => 'pl', 'name' => $names['pl']],
            ]);
            if (isset($names['en'])) {
                DB::table('city_translations')->insert([
                    ['city_id' => $cityId, 'locale' => 'en', 'name' => $names['en']],
                ]);
            }
        }
    }
}
