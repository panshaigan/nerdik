<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\PageAliases\Pages\CreatePageAlias;
use App\Filament\Admin\Resources\PageAliases\Pages\ListPageAliases;
use App\Models\PageAlias;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PageAliasFilamentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_list_page_aliases(): void
    {
        $admin = User::factory()->admin()->create();
        $aliases = PageAlias::factory()->count(2)->create(['created_by_id' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(ListPageAliases::class)
            ->assertCanSeeTableRecords($aliases);
    }

    #[Test]
    public function admin_can_create_page_alias_via_filament(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(CreatePageAlias::class)
            ->fillForm([
                'alias' => 'filament-link',
                'target_path' => '/dashboard',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('page_aliases', [
            'alias' => 'filament-link',
            'target_path' => '/dashboard',
            'created_by_id' => $admin->id,
        ]);
    }
}
