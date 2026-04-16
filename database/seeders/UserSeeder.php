<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

use function unpack;

/**
 * Production seeder for activity types.
 */
class UserSeeder extends Seeder
{
    public function run(array $dataset): void
    {
        $uf = User::factory();

        // These are the users that are always created to make the testing easier
        $uf->admin()
            ->specific('Alice Merton', 'alice', 'alice@nerdik.test')
            ->create();
        $uf->organizer()
            ->specific('Bob Hopkins', 'bob', 'bob@nerdik.test')
            ->create();
        $uf
            ->specific('Charlie Montana', 'charlie', 'charlie@nerdik.test')
            ->create();
        $uf
            ->specific('Diana Harvey', 'diana', 'diana@nerdik.test')
            ->create();

        // We reduce the number of admins and organizers by one, as they are already created
        User::factory($dataset['admins']-1)->admin()->create();
        User::factory($dataset['organizers']-1)->organizer()->create();
        User::factory($dataset['standardUsers']-2)->create();
    }
}
