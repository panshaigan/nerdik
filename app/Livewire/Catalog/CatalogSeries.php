<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\WithCatalogSearch;
use App\Livewire\Concerns\WithCatalogSeriesSort;
use App\Models\ActivitySeries;
use App\Models\EventSeries;
use App\Support\Catalog\CatalogQuery;
use App\Support\Catalog\CatalogSeriesClosestActivityResolver;
use App\Support\Catalog\CatalogSeriesClosestEventResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogSeries extends Component
{
    use WithCatalogSearch;
    use WithCatalogSeriesSort;
    use WithPagination;

    public function render(
        CatalogSeriesClosestEventResolver $closestEventResolver,
        CatalogSeriesClosestActivityResolver $closestActivityResolver,
    ): View {
        $seriesList = CatalogQuery::paginatedSeries(
            $this->q,
            $this->catalogPerPage(),
            $this->getPage(),
            $this->catalogSeriesSortKey(),
            $this->catalogSeriesSortDirection(),
        );

        $eventSeries = collect($seriesList->items())
            ->pluck('eventSeries')
            ->filter(fn (mixed $series): bool => $series instanceof EventSeries)
            ->values();
        $activitySeries = collect($seriesList->items())
            ->pluck('activitySeries')
            ->filter(fn (mixed $series): bool => $series instanceof ActivitySeries)
            ->values();

        return view('livewire.catalog.catalog-series', [
            'seriesList' => $seriesList,
            'eventSeriesCoverPicturesById' => $closestEventResolver->coverPicturesBySeriesId(
                $eventSeries,
            ),
            'activitySeriesCoverPicturesById' => $closestActivityResolver->coverPicturesBySeriesId(
                $activitySeries,
            ),
        ]);
    }
}
