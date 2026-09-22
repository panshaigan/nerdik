<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\WithCatalogSearch;
use App\Support\Catalog\CatalogQuery;
use App\Support\Catalog\CatalogSeriesClosestEventResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogSeries extends Component
{
    use WithCatalogSearch;
    use WithPagination;

    public function render(CatalogSeriesClosestEventResolver $closestEventResolver): View
    {
        $seriesList = CatalogQuery::series($this->q)->paginate($this->catalogPerPage());

        return view('livewire.catalog.catalog-series', [
            'seriesList' => $seriesList,
            'seriesCoverPicturesById' => $closestEventResolver->coverPicturesBySeriesId(
                $seriesList->getCollection(),
            ),
        ]);
    }
}
