<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\City;
use App\Models\Country;
use App\Models\Place;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Str;

use function fake;

/**
 * @extends Factory<Place>
 */
final class PlaceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Place::class;

    /**
     * Define the model's default state.
     */
    #[\Override]
    public function definition(): array
    {
        $name = fake()->name;

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'address' => fake()->optional()->streetAddress(),
            'city_id' => null,
            'description' => fake()->optional()->text,
            'latitude' => null,
            'longitude' => null,
        ];
    }

    public function poland(): self
    {
        return $this->state(fn (array $attributes) => [
            'country_id' => Country::where('iso_alpha2', 'PL')->first()->id,
            'latitude' => fake()->randomFloat(7, 49.00, 54.85),
            'longitude' => fake()->randomFloat(7, 14.12, 24.15),
        ]);
    }

    public function venue(): self
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'venue',
        ]);
    }

    public function room(Place $venue): self
    {
        return $this->afterMaking(function (Place $room) use ($venue) {
            $room->slug = $venue->slug.'-'.Str::slug($room->name);
            $room->address = $venue->address;
            $room->city_id = $venue->city_id;
            $room->country_id = $venue->country_id;
            $room->latitude = $venue->latitude;
            $room->longitude = $venue->longitude;
            $room->parent_id = $venue->id;
            $room->type = 'room';
        });
    }

    public function predefined(): self
    {
        $poland = Country::query()->where('iso_alpha2', 'PL')->first();
        $cities = City::query()
            ->join('city_translations', 'cities.id', '=', 'city_translations.city_id')
            ->where('cities.country_id', $poland->id)
            ->where('city_translations.locale', 'pl')
            ->pluck('cities.id', 'city_translations.name');

        return $this->state(new Sequence(
            [
                'name' => 'Mistrz i Małgorzata',
                'address' => 'Bogusławskiego 10',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1010023',
                'longitude' => '17.0254532',
            ],
            [
                'name' => 'Zapiecek',
                'address' => 'Świętojańska 13',
                'city_id' => $cities['Warszawa'],
                'latitude' => '52.2478',
                'longitude' => '21.0125',
            ],
            [
                'name' => 'Konspira',
                'address' => 'ul. Włodkowica 27',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1102',
                'longitude' => '17.0285',
            ],
            [
                'name' => 'Szara Gęś',
                'address' => 'Rynek Główny 17',
                'city_id' => $cities['Kraków'],
                'latitude' => '50.0614',
                'longitude' => '19.9370',
            ],
            [
                'name' => 'Dinette',
                'address' => 'ul. Świdnicka 36',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1078',
                'longitude' => '17.0312',
            ],
            [
                'name' => 'Pierogarnia Mandu',
                'address' => 'ul. Kaprów 19D',
                'city_id' => $cities['Gdańsk'],
                'latitude' => '54.3520',
                'longitude' => '18.6530',
            ],
            [
                'name' => 'Bułka z Masłem',
                'address' => 'ul. Włodkowica 13',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1095',
                'longitude' => '17.0278',
            ],
            [
                'name' => 'Ptasie Radio',
                'address' => 'ul. Kościuszki 74',
                'city_id' => $cities['Poznań'],
                'latitude' => '52.4069',
                'longitude' => '16.9299',
            ],
            [
                'name' => 'Lwia Brama',
                'address' => 'ul. Katedralna 9',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1148',
                'longitude' => '17.0462',
            ],
            [
                'name' => 'Nolita Restaurant',
                'address' => 'Nowy Świat 32',
                'city_id' => $cities['Warszawa'],
                'latitude' => '52.2305',
                'longitude' => '21.0218',
            ],

            // Block 2 (10 places)
            [
                'name' => 'Piwnica Świdnicka',
                'address' => 'Rynek Ratusz',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1107',
                'longitude' => '17.0323',
            ],
            [
                'name' => 'Pod Aniołami',
                'address' => 'Grodzka 35',
                'city_id' => $cities['Kraków'],
                'latitude' => '50.0582',
                'longitude' => '19.9355',
            ],
            [
                'name' => 'Whiskey in the Jar',
                'address' => 'ul. Rynek 28',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1110',
                'longitude' => '17.0315',
            ],
            [
                'name' => 'Pomelo Bistro',
                'address' => 'ul. Ogarna 27/28',
                'city_id' => $cities['Gdańsk'],
                'latitude' => '54.3508',
                'longitude' => '18.6525',
            ],
            [
                'name' => 'Jadka',
                'address' => 'ul. Rzeźnicza 24',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1123',
                'longitude' => '17.0298',
            ],
            [
                'name' => 'Weranda Caffe',
                'address' => 'Stary Rynek 77',
                'city_id' => $cities['Poznań'],
                'latitude' => '52.4078',
                'longitude' => '16.9342',
            ],
            [
                'name' => 'Spiz',
                'address' => 'ul. Rynek 9',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1109',
                'longitude' => '17.0321',
            ],
            [
                'name' => 'Gniazdo Kawy',
                'address' => 'ul. Kotlarska 32',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1121',
                'longitude' => '17.0345',
            ],
            [
                'name' => 'Alebrowar',
                'address' => 'ul. Słodowa 8',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1155',
                'longitude' => '17.0402',
            ],
            [
                'name' => 'Mleczarnia',
                'address' => 'ul. Włodkowica 5',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1098',
                'longitude' => '17.0281',
            ],

            // Block 3 (10 places)
            [
                'name' => 'Oh My Pasta',
                'address' => 'ul. Oławska 21',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1072',
                'longitude' => '17.0339',
            ],
            [
                'name' => 'Złoty Pies',
                'address' => 'ul. Igielna 14',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1115',
                'longitude' => '17.0308',
            ],
            [
                'name' => 'Wshoku Sushi',
                'address' => 'ul. Rynek 41',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1104',
                'longitude' => '17.0319',
            ],
            [
                'name' => 'Bernard',
                'address' => 'ul. Rynek 29',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1112',
                'longitude' => '17.0317',
            ],
            [
                'name' => 'Panczo',
                'address' => 'ul. Wita Stwosza 15',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1128',
                'longitude' => '17.0332',
            ],
            [
                'name' => 'Solleim',
                'address' => 'ul. Słodowa 10',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1158',
                'longitude' => '17.0395',
            ],
            [
                'name' => 'Cocofli',
                'address' => 'ul. Kazimierza Wielkiego 45',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1089',
                'longitude' => '17.0341',
            ],
            [
                'name' => 'Recepcja',
                'address' => 'ul. Plac Solny 11',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1092',
                'longitude' => '17.0305',
            ],
            [
                'name' => 'Bar Mleczny Miś',
                'address' => 'ul. Kazimierza Wielkiego 39',
                'city_id' => $cities['Wrocław'],
                'latitude' => '51.1085',
                'longitude' => '17.0338',
            ],
        ));
    }
}
