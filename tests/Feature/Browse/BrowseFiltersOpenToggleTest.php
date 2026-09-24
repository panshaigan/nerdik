<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Livewire\Browse\BrowseEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class BrowseFiltersOpenToggleTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_panel_uses_morph_safe_alpine_bindings(): void
    {
        Livewire::test(BrowseEvents::class)
            ->assertDontSeeHtml('x-show="filtersOpen"')
            ->assertDontSeeHtml('wire:click="toggleFiltersOpen"')
            ->assertSeeHtml('x-data="{ filtersOpen: false }"')
            ->assertSeeHtml('x-bind:class="{ \'modal-open !animate-none\': !!$data.filtersOpen }"')
            ->assertSeeHtml('x-on:click="$data.filtersOpen = !$data.filtersOpen"')
            ->assertSeeHtml('data-ui="browse-events-filters-panel"')
            ->assertSeeHtml('ui-overlay-sheet')
            ->assertSeeHtml('data-ui="browse-events-save-search"');
    }
}
