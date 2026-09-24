<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\WithCatalogSearch;
use App\Support\Catalog\CatalogQuery;
use App\Support\Catalog\CatalogSeriesClosestActivityResolver;
use App\Support\Catalog\CatalogSeriesClosestEventResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogSeries extends Component
{
    use WithCatalogSearch;
    use WithPagination;

    public string $kind = 'events';

    protected array $queryString = [
        'kind' => ['except' => 'events'],
    ];

    public function updatedKind(string $value): void
    {
        $this->kind = $this->normalizeKind($value);
        $this->resetPage();
    }

    public function render(
        CatalogSeriesClosestEventResolver $closestEventResolver,
        CatalogSeriesClosestActivityResolver $closestActivityResolver,
    ): View {
        $kind = $this->normalizeKind($this->kind);

        if ($kind === 'activities') {
            $seriesList = CatalogQuery::activitySeries($this->q)->paginate($this->catalogPerPage());
            $seriesCoverPicturesById = $closestActivityResolver->coverPicturesBySeriesId(
                $seriesList->getCollection(),
            );
        } else {
            $seriesList = CatalogQuery::series($this->q)->paginate($this->catalogPerPage());
            $seriesCoverPicturesById = $closestEventResolver->coverPicturesBySeriesId(
                $seriesList->getCollection(),
            );
        }

        return view('livewire.catalog.catalog-series', [
            'kind' => $kind,
            'seriesList' => $seriesList,
            'seriesCoverPicturesById' => $seriesCoverPicturesById,
        ]);
    }

    private function normalizeKind(?string $value): string
    {
        return in_array($value, ['events', 'activities'], true) ? $value : 'events';
    }
}
