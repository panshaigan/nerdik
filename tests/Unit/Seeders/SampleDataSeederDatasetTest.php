<?php

namespace Tests\Unit\Seeders;

use Database\Seeders\SampleDataSeeder;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SampleDataSeederDatasetTest extends TestCase
{
    public function test_resolve_dataset_from_config_maps_known_values(): void
    {
        Config::set('app.seed_dataset', 'standard');
        $this->assertSame(SampleDataSeeder::DATASET_STANDARD, SampleDataSeeder::resolveDatasetFromEnv());

        Config::set('app.seed_dataset', 'maximal');
        $this->assertSame(SampleDataSeeder::DATASET_MAXIMAL, SampleDataSeeder::resolveDatasetFromEnv());

        Config::set('app.seed_dataset', 'minimal');
        $this->assertSame(SampleDataSeeder::DATASET_MINIMAL, SampleDataSeeder::resolveDatasetFromEnv());
    }

    public function test_resolve_dataset_from_config_falls_back_to_minimal_for_unknown_values(): void
    {
        Config::set('app.seed_dataset', 'unknown-volume');

        $this->assertSame(SampleDataSeeder::DATASET_MINIMAL, SampleDataSeeder::resolveDatasetFromEnv());
    }
}
