<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Traits\AuthorizesOwnership;

class OrganizationController extends Controller
{
    use AuthorizesOwnership;

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        abort_unless(auth()->user()?->canCreateEvents(), 403, __('ui.organizations.only_event_organizers_can_manage'));

        return view('organizations.create');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Organization $organization)
    {
        $this->authorizeCreatedBy($organization);

        return view('organizations.edit', compact('organization'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization)
    {
        $this->authorizeCreatedBy($organization);

        $organization->delete();

        return redirect()->route('organizations.index')
            ->with('status', __('Organization deleted.'));
    }
}
