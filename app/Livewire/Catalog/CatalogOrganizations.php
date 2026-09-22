<?php

declare(strict_types=1);

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\WithCatalogSearch;
use App\Models\Organization;
use App\Support\Catalog\CatalogQuery;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogOrganizations extends Component
{
    use WithCatalogSearch;
    use WithPagination;

    public bool $organizationPreviewModalOpen = false;

    public ?int $previewOrganizationId = null;

    public function openOrganizationPreview(int $organizationId): void
    {
        Organization::query()->whereKey($organizationId)->firstOrFail();

        $this->previewOrganizationId = $organizationId;
        $this->organizationPreviewModalOpen = true;
    }

    public function closeOrganizationPreview(): void
    {
        $this->organizationPreviewModalOpen = false;
        $this->previewOrganizationId = null;
    }

    public function updatedOrganizationPreviewModalOpen(bool $value): void
    {
        if (! $value) {
            $this->closeOrganizationPreview();
        }
    }

    public function render(): View
    {
        $previewOrganization = $this->organizationPreviewModalOpen && $this->previewOrganizationId !== null
            ? Organization::query()->whereKey($this->previewOrganizationId)->first()
            : null;

        if ($previewOrganization === null && $this->organizationPreviewModalOpen) {
            $this->closeOrganizationPreview();
        }

        return view('livewire.catalog.catalog-organizations', [
            'organizations' => CatalogQuery::organizations($this->q)->paginate($this->catalogPerPage()),
            'previewOrganization' => $previewOrganization,
        ]);
    }
}
