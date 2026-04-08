<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Place;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

use function now;

class ActivityTypeSeeder extends Seeder
{
    private array $slugs = [
        'rpg',
        'wargame',
        'board',
        'card',
        'larp',
        'discussion',
        'lecture',
        'workshop',
        'competition',
        'show',
    ];

    /**
     * Sample physical venues only. Country/city names come from {@see Country} and {@see City}, not from place rows.
     */
    public function run(): void
    {
        $this->seedActivityTypes();
    }

    private function seedActivityTypes(): void
    {
        foreach ($this->slugs as $slug) {
            DB::table('activity_types')->updateOrInsert(['slug' => $slug], ['slug' => $slug]);
        }
    }
}
