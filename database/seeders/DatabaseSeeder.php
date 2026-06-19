<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seed DB
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BaseDataSeeder::class);

        $dataset = SampleDataSeeder::DATASETS[SampleDataSeeder::resolveDatasetFromEnv()];

        $this->callWith(UserSeeder::class, ['dataset' => $dataset]);
        $this->callWith(PlaceSeeder::class, ['dataset' => $dataset]);
        $this->callWith(SampleDataSeeder::class, [
            'chosenDataset' => SampleDataSeeder::resolveDatasetFromEnv(),
        ]);
    }
}
