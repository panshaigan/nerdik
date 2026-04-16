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
    public function run(): void
    {
        $this->seedPolishCities();
        $data = $this->getPlacesData();
        $venues = [];

        foreach ($data as $place) {
            $venues[] = Place::factory()
                ->poland()
                ->venue()
                ->state($place)
                ->create();
        }

        foreach ($venues as $venue) {
            Place::factory(3)
                ->poland()
                ->room()
                ->sequence(
                    ['name' => 'Room A'],
                    ['name' => 'Room B'],
                    ['name' => 'Room C'],
                )
                ->afterMaking(function (Place $place) use ($venue) {
                    $place->slug = $venue->slug.'-'.Str::slug($place->name);
                    $place->address = $venue->address;
                    $place->city_id = $venue->city_id;
                    $place->country_id = $venue->country_id;
                    $place->latitude = $venue->latitude;
                    $place->longitude = $venue->longitude;
                    $place->parent_id = $venue->id;
                })
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

    private function cityByEnglishName(int $countryId, string $englishName): ?City
    {
        return City::query()
            ->where('country_id', $countryId)
            ->whereHas('translations', function ($q) use ($englishName) {
                $q->where('locale', 'en')->where('name', $englishName);
            })
            ->first();
    }

    private function getPlacesData(): array
    {
        $poland = Country::query()->where('iso_alpha2', 'PL')->first();
        $cities = City::query()
            ->join('city_translations', 'cities.id', '=', 'city_translations.city_id')
            ->where('cities.country_id', $poland->id)
            ->where('city_translations.locale', 'pl')
            ->pluck('cities.id', 'city_translations.name');

        return [
            // Wrocław
            [
                'name' => 'Mistrz i Małgorzata',
                'address' => 'Bogusławskiego 10',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1010023',
                'longitude' => '17.0254532',
            ],
            [
                'name' => 'Pierogarnia Stary Młyn',
                'address' => 'Rynek 26',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1105',
                'longitude' => '17.0318',
            ],
            [
                'name' => 'Konspira',
                'address' => 'ul. Włodkowica 27',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1102',
                'longitude' => '17.0285',
            ],
            [
                'name' => 'Dinette',
                'address' => 'ul. Świdnicka 36',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1078',
                'longitude' => '17.0312',
            ],
            [
                'name' => 'Bułka z Masłem',
                'address' => 'ul. Włodkowica 13',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1095',
                'longitude' => '17.0278',
            ],
            [
                'name' => 'Lwia Brama',
                'address' => 'ul. Katedralna 9',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1148',
                'longitude' => '17.0462',
            ],
            [
                'name' => 'Piwnica Świdnicka',
                'address' => 'Rynek Ratusz',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1107',
                'longitude' => '17.0323',
            ],
            [
                'name' => 'Whiskey in the Jar',
                'address' => 'ul. Rynek 28',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1110',
                'longitude' => '17.0315',
            ],
            [
                'name' => 'Bar Mleczny Miś',
                'address' => 'ul. Kazimierza Wielkiego 39',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1085',
                'longitude' => '17.0338',
            ],
            [
                'name' => 'Jadka',
                'address' => 'ul. Rzeźnicza 24',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1123',
                'longitude' => '17.0298',
            ],
            [
                'name' => 'Spiz',
                'address' => 'ul. Rynek 9',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1109',
                'longitude' => '17.0321',
            ],
            [
                'name' => 'Gniazdo Kawy',
                'address' => 'ul. Kotlarska 32',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1121',
                'longitude' => '17.0345',
            ],
            [
                'name' => 'Alebrowar',
                'address' => 'ul. Słodowa 8',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1155',
                'longitude' => '17.0402',
            ],
            [
                'name' => 'Mleczarnia',
                'address' => 'ul. Włodkowica 5',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1098',
                'longitude' => '17.0281',
            ],
            [
                'name' => 'Oh My Pasta',
                'address' => 'ul. Oławska 21',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1072',
                'longitude' => '17.0339',
            ],
            [
                'name' => 'Złoty Pies',
                'address' => 'ul. Igielna 14',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1115',
                'longitude' => '17.0308',
            ],
            [
                'name' => 'Wshoku Sushi',
                'address' => 'ul. Rynek 41',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1104',
                'longitude' => '17.0319',
            ],
            [
                'name' => 'Bernard',
                'address' => 'ul. Rynek 29',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1112',
                'longitude' => '17.0317',
            ],
            [
                'name' => 'Panczo',
                'address' => 'ul. Wita Stwosza 15',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1128',
                'longitude' => '17.0332',
            ],
            [
                'name' => 'Solleim',
                'address' => 'ul. Słodowa 10',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1158',
                'longitude' => '17.0395',
            ],
            [
                'name' => 'Cocofli',
                'address' => 'ul. Kazimierza Wielkiego 45',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1089',
                'longitude' => '17.0341',
            ],
            [
                'name' => 'Recepcja',
                'address' => 'ul. Plac Solny 11',
                'city_id' => $cities['Wrocław'],
                'latitude'  => '51.1092',
                'longitude' => '17.0305',
            ],

            // Warszawa (Warsaw)
            [
                'name' => 'Zapiecek',
                'address' => 'Świętojańska 13',
                'city_id' => $cities['Warszawa'],
                'latitude'  => '52.2478',
                'longitude' => '21.0125',
            ],
            [
                'name' => 'Nolita Restaurant',
                'address' => 'Nowy Świat 32',
                'city_id' => $cities['Warszawa'],
                'latitude'  => '52.2305',
                'longitude' => '21.0218',
            ],

            // Gdańsk
            [
                'name' => 'Pierogarnia Mandu',
                'address' => 'ul. Kaprów 19D',
                'city_id' => $cities['Gdańsk'],
                'latitude'  => '54.3520',
                'longitude' => '18.6530',
            ],
            [
                'name' => 'Pomelo Bistro',
                'address' => 'ul. Ogarna 27/28',
                'city_id' => $cities['Gdańsk'],
                'latitude'  => '54.3508',
                'longitude' => '18.6525',
            ],

            // Poznań
            [
                'name' => 'Ptasie Radio',
                'address' => 'ul. Kościuszki 74',
                'city_id' => $cities['Poznań'],
                'latitude'  => '52.4069',
                'longitude' => '16.9299',
            ],
            [
                'name' => 'Weranda Caffe',
                'address' => 'Stary Rynek 77',
                'city_id' => $cities['Poznań'],
                'latitude'  => '52.4078',
                'longitude' => '16.9342',
            ],

            // Kraków
            [
                'name' => 'Szara Gęś',
                'address' => 'Rynek Główny 17',
                'city_id' => $cities['Kraków'],
                'latitude'  => '50.0614',
                'longitude' => '19.9370',
            ],
            [
                'name' => 'Pod Aniołami',
                'address' => 'Grodzka 35',
                'city_id' => $cities['Kraków'],
                'latitude'  => '50.0582',
                'longitude' => '19.9355',
            ],
        ];
    }
}
