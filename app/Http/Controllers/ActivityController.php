<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Traits\AuthorizesOwnership;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ActivityController extends Controller
{
    use AuthorizesOwnership;

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('activities.create');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Activity $activity): View
    {
        $this->authorizeCreatedBy($activity);

        $activity->load('tags');

        return view('activities.edit', compact('activity'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity): RedirectResponse
    {
        $this->authorizeCreatedBy($activity);

        $activity->delete();

        return redirect()->route('search.index')
            ->with('status', __('Activity deleted.'));
    }
}
