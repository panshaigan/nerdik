<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrganizationSeeder extends Seeder
{
    const ALICE = 'alice@nerdik.test';
    const BOB = 'bob@nerdik.test';
    const CHARLIE = 'charlie@nerdik.test';
    const DIANA = 'diana@nerdik.test';

    /**
     * Sample physical venues only. Country/city names come from {@see Country} and {@see City}, not from place rows.
     */
    public function run(): void
    {
        $this->seedStandardOrganizations();
        $this->seedRandomOrganizations();
    }

    public function seedStandardOrganizations()
    {
        $alice = User::firstOrCreate(['email' => UserSeeder::ALICE]);
        $bob = User::firstOrCreate(['email' => UserSeeder::BOB]);
        $charlie = User::firstOrCreate(['email' => UserSeeder::CHARLIE]);
        $diana = User::firstOrCreate(['email' => UserSeeder::DIANA]);

        $org = Organization::firstOrCreate(
            ['slug' => 'nerdik-club'],
            [
                'name' => 'Nerdik Club',
                'description' => 'Local RPG & board game club.',
                'created_by' => $alice->id,
            ]
        );

        $org2 = Organization::firstOrCreate(
            ['slug' => 'wroclaw-gamers'],
            [
                'name' => 'Wrocław Gamers',
                'description' => 'Community for tabletop gamers in Wrocław.',
                'created_by' => $bob->id,
            ]
        );
    }

    private function seedRandomOrganizations(): void
    {
        $factory = User::factory();
        $factory->count(30)->create();
    }
}
