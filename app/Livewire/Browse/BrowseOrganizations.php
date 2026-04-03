<?php

namespace App\Livewire\Browse;

use App\Models\Organization;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BrowseOrganizations extends Component
{
    use WithPagination;

    #[Url]
    public string $q = '';

    public function applySearch(): void
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->resetPage();
        $this->reset(['q']);

        return $this->redirectRoute('organizations.index');
    }

    public function hasActiveFilters(): bool
    {
        return $this->q !== '';
    }

    public function render()
    {
        $query = Organization::with('creator')
            ->orderBy('name');

        if ($this->q !== '') {
            $term = '%'.$this->q.'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('desc', 'like', $term));
        }

        $organizations = $query->paginate(12);

        return view('livewire.browse.browse-organizations', [
            'organizations' => $organizations,
        ]);
    }
}
