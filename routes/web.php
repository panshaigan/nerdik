<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityProposalController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\InterestController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\TagController;
use App\Models\Activity;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('locale/{locale}', function (Request $request, string $locale) {
    abort_unless(in_array($locale, ['en', 'pl'], true), 404);

    session(['locale' => $locale]);

    $redirectTo = (string) $request->query('redirect', '');
    if (! str_starts_with($redirectTo, '/')) {
        $redirectTo = route('dashboard');
    }

    return redirect($redirectTo)->cookie('locale', $locale, 60 * 24 * 365);
})->name('locale.switch');

/*
| Public unified browse (events + activities). Legacy /events and /activities redirect to /search.
| Organizations are listed only for the signed-in owner (see authenticated `organizations.index`).
*/
Route::view('search', 'browse.events')->name('search.index');

Route::redirect('events', '/search', 301);
Route::redirect('activities', '/search', 301);
Route::redirect('browse/events', '/search', 301);
Route::redirect('browse/activities', '/search', 301);
Route::redirect('browse/organizations', '/organizations', 301);

Route::get('geocode/reverse', [GeocodeController::class, 'reverse'])
    ->middleware('throttle:60,1')
    ->name('geocode.reverse');

Route::get('geocode/search', [GeocodeController::class, 'search'])
    ->middleware('throttle:30,1')
    ->name('geocode.search');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function () {
    Route::redirect('slots', '/dashboard', 301);

    Route::view('organizations', 'organizations.index')->name('organizations.index');

    Route::resource('organizations', OrganizationController::class)
        ->except(['show', 'index']);

    Route::get('events/{event}/propose', function (Event $event) {
        return view('activity-proposals.create', compact('event'));
    })->name('events.propose');
    Route::post('events/{event}/copy', [EventController::class, 'copy'])->name('events.copy');
    Route::post('events/{event}/slots/mass', [EventController::class, 'massStoreSlots'])->name('events.slots.mass');
    Route::resource('events', EventController::class)->except(['show', 'store', 'update', 'index']);

    // Slot edit/update only (modal fetch + form POST). Create/list/destroy are via event UI or Filament admin.
    Route::get('slots/{slot}/edit', [SlotController::class, 'edit'])->name('slots.edit');
    Route::put('slots/{slot}', [SlotController::class, 'update'])->name('slots.update');

    Route::get('places/{venueId}/rooms', [PlaceController::class, 'roomsForVenue'])
        ->whereNumber('venueId')
        ->name('places.rooms');

    Route::resource('places', PlaceController::class)
        ->except(['show']);

    Route::resource('tags', TagController::class)
        ->except(['show']);

    Route::resource('activities', ActivityController::class)->except(['store', 'update', 'show', 'index']);
    Route::get('activities/{activity}', function (Activity $activity) {
        return view('activities.show', compact('activity'));
    })->name('activities.show');

    Route::view('activity-proposals', 'activity-proposals.index')->name('activity-proposals.index');
    Route::post('activity-proposals/{proposal}/accept', [ActivityProposalController::class, 'accept'])->name('activity-proposals.accept');
    Route::post('activity-proposals/{proposal}/reject', [ActivityProposalController::class, 'reject'])->name('activity-proposals.reject');

    Route::post('activities/{activity}/join', [ParticipationController::class, 'join'])->name('activities.join');
    Route::post('activities/{activity}/leave', [ParticipationController::class, 'leave'])->name('activities.leave');
    Route::post('activities/{activity}/join-waitlist', [ParticipationController::class, 'joinWaitlist'])->name('activities.join-waitlist');
    Route::post('activities/{activity}/leave-waitlist', [ParticipationController::class, 'leaveWaitlist'])->name('activities.leave-waitlist');
    Route::post('activities/{activity}/waitlist/{entry}/approve', [ParticipationController::class, 'approveWaitlistEntry'])
        ->name('activities.waitlist.approve');
    Route::post('activity-participants/{participant}/mark-absent', [ParticipationController::class, 'markAbsent'])->name('activity-participants.mark-absent');
    Route::post('activity-participants/{participant}/unmark-absent', [ParticipationController::class, 'unmarkAbsent'])->name('activity-participants.unmark-absent');
    Route::post('activity-participants/{participant}/move-to-waitlist', [ParticipationController::class, 'moveParticipantToWaitlist'])->name('activity-participants.move-to-waitlist');
    Route::post('activity-participants/{participant}/remove', [ParticipationController::class, 'removeParticipant'])->name('activity-participants.remove');

    Route::post('events/{event}/interests', [InterestController::class, 'addEvent'])->name('interests.events.add');
    Route::delete('events/{event}/interests', [InterestController::class, 'removeEvent'])->name('interests.events.remove');
    Route::post('activities/{activity}/interests', [InterestController::class, 'addActivity'])->name('interests.activities.add');
    Route::delete('activities/{activity}/interests', [InterestController::class, 'removeActivity'])->name('interests.activities.remove');

    Route::view('notifications', 'notifications.index')->name('notifications.index');
});

// Public event detail route.
// Must be declared after more specific routes like `events/create` and `events/*/edit`,
// otherwise `events/{event}` can consume `create` as the `{event}` slug.
Route::get('events/{event}', function (Event $event) {
    return view('events.show', compact('event'));
})->name('events.show');

require __DIR__.'/auth.php';
