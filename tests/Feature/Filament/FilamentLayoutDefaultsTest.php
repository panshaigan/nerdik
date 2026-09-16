<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\ActivityProposalSlots\Pages\ListActivityProposalSlots;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FilamentLayoutDefaultsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_panel_uses_full_content_width(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertSame(Width::Full, Filament::getMaxContentWidth());
    }

    #[Test]
    public function admin_tables_default_to_fifty_records_per_page(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(ListActivityProposalSlots::class)
            ->assertOk()
            ->assertSet('tableRecordsPerPage', 50);
    }
}
