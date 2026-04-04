<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Traits\AuthorizesOwnership;

class ActivityController extends Controller
{
    use AuthorizesOwnership;

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('activities.create');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Activity $activity)
    {
        $this->authorizeCreatedBy($activity);

        $activity->load('tags');

        return view('activities.edit', compact('activity'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Activity $activity)
    {
        $this->authorizeCreatedBy($activity);

        $activity->delete();

        return redirect()->route('activities.index')
            ->with('status', __('Activity deleted.'));
    }
}
