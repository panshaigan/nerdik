<?php

namespace Database\Seeders;

use App\Models\Place;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlaceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Basic countries and one city/country for Poland; extend as needed.
        $poland = Place::firstOrCreate(
            ['slug' => 'poland'],
            [
                'name' => 'Poland',
                'type' => 'country',
                'is_online' => false,
            ]
        );

        // Example Polish cities; you can extend this list.
        $cities = [
            'warszawa' => 'Warszawa',
            'krakow' => 'Kraków',
            'wroclaw' => 'Wrocław',
            'poznan' => 'Poznań',
            'gdansk' => 'Gdańsk',
        ];

        foreach ($cities as $slug => $name) {
            Place::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'type' => 'city',
                    'parent_id' => $poland->id,
                    'is_online' => false,
                ]
            );
        }
    }
}
