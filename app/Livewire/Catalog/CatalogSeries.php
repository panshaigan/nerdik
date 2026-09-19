<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\WithCatalogSearch;
use App\Support\Catalog\CatalogQuery;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogSeries extends Component
{
    use WithCatalogSearch;
    use WithPagination;

    public function render(): View
    {
        return view('livewire.catalog.catalog-series', [
            'seriesList' => CatalogQuery::series($this->q)->paginate($this->catalogPerPage()),
        ]);
    }
}
