<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BaseDataSeeder extends Seeder
{
    /**
     * Seed base/required data for production.
     */
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,
            TagSeeder::class,
        ]);
    }
}
