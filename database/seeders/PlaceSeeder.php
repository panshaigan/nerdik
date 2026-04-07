<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Place;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlaceSeeder extends Seeder
{
    /**
     * Sample physical venues only. Country/city names come from {@see Country} and {@see City}, not from place rows.
     */
    public function run(): void
    {
        $this->seedPolishCities();

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

    private function seedPolishCities(): void
    {
        $plId = DB::table('countries')->where('iso_alpha2', 'PL')->value('id');
        if (! $plId) {
            return;
        }

        $cities = [
            ['en' => 'Warsaw', 'pl' => 'Warszawa'],
            ['en' => 'Kraków', 'pl' => 'Kraków'],
            ['en' => 'Wrocław', 'pl' => 'Wrocław'],
            ['en' => 'Poznań', 'pl' => 'Poznań'],
            ['en' => 'Gdańsk', 'pl' => 'Gdańsk'],
            ['en' => 'Szczecin', 'pl' => 'Szczecin'],
            ['en' => 'Bydgoszcz', 'pl' => 'Bydgoszcz'],
            ['en' => 'Lublin', 'pl' => 'Lublin'],
            ['en' => 'Katowice', 'pl' => 'Katowice'],
            ['en' => 'Białystok', 'pl' => 'Białystok'],
            ['en' => 'Gdynia', 'pl' => 'Gdynia'],
            ['en' => 'Częstochowa', 'pl' => 'Częstochowa'],
            ['en' => 'Radom', 'pl' => 'Radom'],
            ['en' => 'Sosnowiec', 'pl' => 'Sosnowiec'],
            ['en' => 'Toruń', 'pl' => 'Toruń'],
            ['en' => 'Kielce', 'pl' => 'Kielce'],
            ['en' => 'Gliwice', 'pl' => 'Gliwice'],
            ['en' => 'Zabrze', 'pl' => 'Zabrze'],
            ['en' => 'Olsztyn', 'pl' => 'Olsztyn'],
            ['en' => 'Bielsko-Biała', 'pl' => 'Bielsko-Biała'],
            ['en' => 'Bytom', 'pl' => 'Bytom'],
            ['en' => 'Rzeszów', 'pl' => 'Rzeszów'],
            ['en' => 'Ruda Śląska', 'pl' => 'Ruda Śląska'],
            ['en' => 'Rybnik', 'pl' => 'Rybnik'],
            ['en' => 'Tychy', 'pl' => 'Tychy'],
            ['en' => 'Opole', 'pl' => 'Opole'],
            ['en' => 'Elbląg', 'pl' => 'Elbląg'],
            ['en' => 'Gorzów Wielkopolski', 'pl' => 'Gorzów Wielkopolski'],
            ['en' => 'Włocławek', 'pl' => 'Włocławek'],
            ['en' => 'Tarnów', 'pl' => 'Tarnów'],
            ['en' => 'Chorzów', 'pl' => 'Chorzów'],
            ['en' => 'Kalisz', 'pl' => 'Kalisz'],
            ['en' => 'Koszalin', 'pl' => 'Koszalin'],
            ['en' => 'Legnica', 'pl' => 'Legnica'],
            ['en' => 'Grudziądz', 'pl' => 'Grudziądz'],
            ['en' => 'Słupsk', 'pl' => 'Słupsk'],
            ['en' => 'Jaworzno', 'pl' => 'Jaworzno'],
            ['en' => 'Jastrzębie-Zdrój', 'pl' => 'Jastrzębie-Zdrój'],
            ['en' => 'Jelenia Góra', 'pl' => 'Jelenia Góra'],
            ['en' => 'Nowy Sącz', 'pl' => 'Nowy Sącz'],
            ['en' => 'Konin', 'pl' => 'Konin'],
            ['en' => 'Piotrków Trybunalski', 'pl' => 'Piotrków Trybunalski'],
            ['en' => 'Lubin', 'pl' => 'Lubin'],
            ['en' => 'Ostrołęka', 'pl' => 'Ostrołęka'],
            ['en' => 'Stargard', 'pl' => 'Stargard'],
            ['en' => 'Mysłowice', 'pl' => 'Mysłowice'],
            ['en' => 'Płock', 'pl' => 'Płock'],
            ['en' => 'Łódź', 'pl' => 'Łódź'],
        ];

        foreach ($cities as $names) {
            $cityId = DB::table('cities')->insertGetId([
                'country_id' => $plId,
            ]);
            DB::table('city_translations')->insert([
                ['city_id' => $cityId, 'locale' => 'en', 'name' => $names['en']],
                ['city_id' => $cityId, 'locale' => 'pl', 'name' => $names['pl']],
            ]);
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
