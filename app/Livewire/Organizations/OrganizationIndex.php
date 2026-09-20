<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\DeleteUploadedOrganizationLogo;
use App\Models\Organization;
use App\Traits\AuthorizesOwnership;
use Livewire\Component;

class OrganizationIndex extends Component
{
    use AuthorizesOwnership;

    public function mount(): void
    {
        $this->assertCanManageOrganizations();
    }

    public function deleteOrganization(int $id): void
    {
        $organization = Organization::query()->findOrFail($id);
        $this->authorizeCreatedBy($organization);
        app(DeleteUploadedOrganizationLogo::class)($organization);
        $organization->delete();
        session()->flash('status', __('Organization deleted.'));
    }

    protected function assertCanManageOrganizations(): void
    {
        abort_unless(auth()->user()?->canCreateEvents(), 403, __('ui.organizations.only_event_organizers_can_manage'));
    }

    public function render()
    {
        $organizations = Organization::query()
            ->where('created_by', auth()->id())
            ->orderBy('name')
            ->get();

        return view('livewire.organizations.organization-index', [
            'organizations' => $organizations,
        ]);
    }
}
