<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityProposalController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\WishlistController;
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

Route::view('browse/events', 'browse.events')->name('browse.events');
Route::view('browse/activities', 'browse.activities')->name('browse.activities');
Route::view('browse/organizations', 'browse.organizations')->name('browse.organizations');

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
    Route::resource('organizations', OrganizationController::class)
        ->except(['show']);

    Route::get('events/{event}/propose', function (Event $event) {
        return view('activity-proposals.create', compact('event'));
    })->name('events.propose');
    Route::post('events/{event}/copy', [EventController::class, 'copy'])->name('events.copy');
    Route::resource('events', EventController::class)->except(['show', 'store', 'update']);

    Route::resource('slots', SlotController::class)
        ->except(['show']);

    Route::resource('places', PlaceController::class)
        ->except(['show']);

    Route::resource('tags', TagController::class)
        ->except(['show']);

    Route::resource('activities', ActivityController::class)->except(['store', 'update']);

    Route::view('activity-proposals', 'activity-proposals.index')->name('activity-proposals.index');
    Route::post('activity-proposals/{proposal}/accept', [ActivityProposalController::class, 'accept'])->name('activity-proposals.accept');
    Route::post('activity-proposals/{proposal}/reject', [ActivityProposalController::class, 'reject'])->name('activity-proposals.reject');

    Route::post('activities/{activity}/join', [ParticipationController::class, 'join'])->name('activities.join');
    Route::post('activities/{activity}/leave', [ParticipationController::class, 'leave'])->name('activities.leave');
    Route::post('activities/{activity}/join-waitlist', [ParticipationController::class, 'joinWaitlist'])->name('activities.join-waitlist');
    Route::post('activities/{activity}/leave-waitlist', [ParticipationController::class, 'leaveWaitlist'])->name('activities.leave-waitlist');
    Route::post('activity-participants/{participant}/mark-absent', [ParticipationController::class, 'markAbsent'])->name('activity-participants.mark-absent');

    Route::post('events/{event}/wishlist', [WishlistController::class, 'addEvent'])->name('wishlist.events.add');
    Route::delete('events/{event}/wishlist', [WishlistController::class, 'removeEvent'])->name('wishlist.events.remove');
    Route::post('activities/{activity}/wishlist', [WishlistController::class, 'addActivity'])->name('wishlist.activities.add');
    Route::delete('activities/{activity}/wishlist', [WishlistController::class, 'removeActivity'])->name('wishlist.activities.remove');

    Route::view('notifications', 'notifications.index')->name('notifications.index');
});

// Public event detail route.
// Must be declared after more specific routes like `events/create` and `events/*/edit`,
// otherwise `events/{event}` can consume `create` as the `{event}` slug.
Route::get('events/{event}', [EventController::class, 'show'])->name('events.show');

require __DIR__.'/auth.php';
