<?php

namespace App\Livewire\Organizations;

use App\Models\Organization;
use App\Support\RichText;
use App\Traits\AuthorizesOwnership;
use Livewire\Component;

class OrganizationIndex extends Component
{
    use AuthorizesOwnership;

    public bool $modalOpen = false;

    /** @var 'create'|'edit' */
    public string $modalMode = 'create';

    public ?int $editingOrganizationId = null;

    public string $name = '';

    public string $desc = '';

    public function mount(): void
    {
        $editSlug = request()->query('edit');
        if (is_string($editSlug) && $editSlug !== '') {
            $organization = Organization::query()->where('slug', $editSlug)->first();
            if ($organization !== null) {
                $this->openEditModal($organization->id);
            }

            return;
        }

        if (request()->query('create') === '1') {
            $this->openCreateModal();
        }
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->modalMode = 'create';
        $this->modalOpen = true;
        $this->scheduleTinyMceModalRefresh();
    }

    public function openEditModal(int $id): void
    {
        $organization = Organization::query()->findOrFail($id);
        $this->authorizeCreatedBy($organization);

        $this->modalMode = 'edit';
        $this->editingOrganizationId = $organization->id;
        $this->name = $organization->name;
        $this->desc = (string) ($organization->desc ?? '');
        $this->resetErrorBag();
        $this->modalOpen = true;
        $this->scheduleTinyMceModalRefresh();
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
        ]);

        $payload = [
            'name' => $this->name,
            'desc' => RichText::sanitize($this->desc),
        ];

        if ($this->modalMode === 'create') {
            Organization::create($payload);
            session()->flash('status', __('Organization created.'));
        } else {
            $organization = Organization::query()->findOrFail($this->editingOrganizationId);
            $this->authorizeCreatedBy($organization);
            $organization->update($payload);
            session()->flash('status', __('Organization updated.'));
        }

        $this->closeModal();
    }

    public function deleteOrganization(int $id): void
    {
        $organization = Organization::query()->findOrFail($id);
        $this->authorizeCreatedBy($organization);
        $organization->delete();
        session()->flash('status', __('Organization deleted.'));
    }

    /**
     * Mary/TinyMCE init runs before the DaisyUI dialog is fully visible; refresh after paint so
     * the editor works for both create and edit (including existing HTML in description).
     */
    protected function scheduleTinyMceModalRefresh(): void
    {
        $this->js(<<<'JS'
            queueMicrotask(() => {
                window.refreshNerdikOrgModalTinyMCE?.();
                requestAnimationFrame(() => {
                    window.refreshNerdikOrgModalTinyMCE?.();
                    setTimeout(() => window.refreshNerdikOrgModalTinyMCE?.(), 120);
                    setTimeout(() => window.refreshNerdikOrgModalTinyMCE?.(), 320);
                });
            });
        JS);
    }

    protected function resetForm(): void
    {
        $this->modalMode = 'create';
        $this->editingOrganizationId = null;
        $this->name = '';
        $this->desc = '';
        $this->resetErrorBag();
    }

    public function render()
    {
        $organizationsQuery = Organization::query()->orderBy('name');
        if (! auth()->user()->is_admin) {
            $organizationsQuery->where('created_by', auth()->id());
        }
        $organizations = $organizationsQuery->get();

        return view('livewire.organizations.organization-index', [
            'organizations' => $organizations,
        ]);
    }
}
