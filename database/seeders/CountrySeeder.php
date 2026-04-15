<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Production Country seeder.
 */
class CountrySeeder extends Seeder
{
    private array $countries = [
        ['iso' => 'PL', 'en' => 'Poland', 'pl' => 'Polska'],
        ['iso' => 'DE', 'en' => 'Germany', 'pl' => 'Niemcy'],
        ['iso' => 'CZ', 'en' => 'Czechia', 'pl' => 'Czechy'],
        ['iso' => 'SK', 'en' => 'Slovakia', 'pl' => 'Słowacja'],
        ['iso' => 'UA', 'en' => 'Ukraine', 'pl' => 'Ukraina'],
        ['iso' => 'GB', 'en' => 'United Kingdom', 'pl' => 'Wielka Brytania'],
        ['iso' => 'US', 'en' => 'United States', 'pl' => 'Stany Zjednoczone'],
        ['iso' => 'FR', 'en' => 'France', 'pl' => 'Francja'],
        ['iso' => 'NL', 'en' => 'Netherlands', 'pl' => 'Holandia'],
        ['iso' => 'BE', 'en' => 'Belgium', 'pl' => 'Belgia'],
        ['iso' => 'AT', 'en' => 'Austria', 'pl' => 'Austria'],
        ['iso' => 'SE', 'en' => 'Sweden', 'pl' => 'Szwecja'],
        ['iso' => 'NO', 'en' => 'Norway', 'pl' => 'Norwegia'],
        ['iso' => 'DK', 'en' => 'Denmark', 'pl' => 'Dania'],
        ['iso' => 'FI', 'en' => 'Finland', 'pl' => 'Finlandia'],
        ['iso' => 'EE', 'en' => 'Estonia', 'pl' => 'Estonia'],
        ['iso' => 'LV', 'en' => 'Latvia', 'pl' => 'Łotwa'],
        ['iso' => 'LT', 'en' => 'Lithuania', 'pl' => 'Litwa'],
        ['iso' => 'RO', 'en' => 'Romania', 'pl' => 'Rumunia'],
        ['iso' => 'HU', 'en' => 'Hungary', 'pl' => 'Węgry'],
        ['iso' => 'AU', 'en' => 'Australia', 'pl' => 'Australia'],
        ['iso' => 'IT', 'en' => 'Italy', 'pl' => 'Włochy'],
        ['iso' => 'ES', 'en' => 'Spain', 'pl' => 'Hiszpania'],
    ];

    public function run(): void
    {
        foreach ($this->countries as $row) {
            $id = DB::table('countries')->insertGetId([
                'iso_alpha2' => $row['iso'],
            ]);
            DB::table('country_translations')->insert([
                ['country_id' => $id, 'locale' => 'en', 'name' => $row['en']],
                ['country_id' => $id, 'locale' => 'pl', 'name' => $row['pl']],
            ]);
        }
    }
}
