<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Support\RichText;
use App\Traits\AuthorizesOwnership;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    use AuthorizesOwnership;

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return redirect()->route('organizations.index', ['create' => '1']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
        ]);

        $validated['desc'] = RichText::sanitize($validated['desc'] ?? null);

        Organization::create($validated);

        return redirect()->route('organizations.index')
            ->with('status', __('Organization created.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Organization $organization)
    {
        $this->authorizeCreatedBy($organization);

        return redirect()->route('organizations.index', ['edit' => $organization->slug]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Organization $organization)
    {
        $this->authorizeCreatedBy($organization);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'desc' => ['nullable', 'string'],
        ]);

        $validated['desc'] = RichText::sanitize($validated['desc'] ?? null);

        $organization->update($validated);

        return redirect()->route('organizations.index')
            ->with('status', __('Organization updated.'));
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
