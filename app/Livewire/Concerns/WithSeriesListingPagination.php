<?php

namespace App\Livewire\Concerns;

use Livewire\WithPagination;

trait WithSeriesListingPagination
{
    use WithPagination;

    public function seriesEventsPageName(): string
    {
        return 'seriesEventsPage';
    }

    public function seriesActivitiesPageName(): string
    {
        return 'seriesActivitiesPage';
    }

    protected function seriesListingsPerPage(): int
    {
        return max(1, (int) config('browse.listings_per_page', 21));
    }

    protected function resetSeriesEventsPage(): void
    {
        $this->resetPage($this->seriesEventsPageName());
    }

    protected function resetSeriesActivitiesPage(): void
    {
        $this->resetPage($this->seriesActivitiesPageName());
    }
}
