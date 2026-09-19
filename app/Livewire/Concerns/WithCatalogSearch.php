<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

trait WithCatalogSearch
{
    #[Url]
    public string $q = '';

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    protected function catalogPerPage(): int
    {
        return (int) config('browse.listings_per_page', 21);
    }
}
