<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ListUsersTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    #[Test]
    public function users_list_defaults_to_newest_id_first(): void
    {
        $older = User::factory()->create(['name' => 'Older Member']);
        $newer = User::factory()->create(['name' => 'Newer Member']);
        $admin = User::factory()->admin()->create();

        $this->assertTrue($newer->id > $older->id);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
    }
}
