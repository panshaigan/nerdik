<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production seeder for activity types.
 */
class UserSeeder extends Seeder
{
    public function run(array $dataset): void
    {
        $uf = User::factory();

        $uf->specific('Alice Merton', 'alice', 'alice@nerdik.test')
            ->admin()->create();
        $uf->specific('Bob Hopkins', 'bob', 'bob@nerdik.test')
            ->organizer()->create();
        $uf->specific('Charlie Montana', 'charlie', 'charlie@nerdik.test')
            ->create();
        $uf->specific('Diana Harvey', 'diana', 'diana@nerdik.test')
            ->create();

        User::factory($dataset['admins']-1)->admin()->create();
        User::factory($dataset['organizers']-1)->organizer()->create();
        User::factory($dataset['normalUsers']-2)->create();
    }
}
