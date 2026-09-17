<?php

use App\Actions\Locale\SwitchLocale;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityProposalController;
use App\Http\Controllers\Browse\BrowseMapFeaturesController;
use App\Http\Controllers\DownloadActivityCalendarController;
use App\Http\Controllers\DownloadActivityParticipantsPdfController;
use App\Http\Controllers\DownloadEventCalendarController;
use App\Http\Controllers\DownloadEventParticipantsPdfController;
use App\Http\Controllers\DownloadEventSeriesCalendarController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\FeedbackEditorUploadController;
use App\Http\Controllers\GeocodeController;
use App\Http\Controllers\InterestController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\RobotsTxtController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\TagController;
use App\Models\Activity;
use App\Models\Event;
use App\Models\EventSeries;
use App\Services\Welcome\WelcomePageDataService;
use App\Support\Seo\Seo;
use Illuminate\Support\Facades\Route;

Route::get('robots.txt', RobotsTxtController::class)->name('robots');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');

Route::get('/', function (WelcomePageDataService $welcome) {
    return view('welcome', $welcome->data());
})->middleware('guest');

Route::get('locale/{locale}', SwitchLocale::class)->name('locale.switch');

/*
| Public unified browse (events + activities). Legacy /events and /activities redirect to /search.
| Organizations are listed only for the signed-in owner (see authenticated `organizations.index`).
*/
Route::view('search', 'browse.events')->name('search.index');

Route::get('search/map-features', BrowseMapFeaturesController::class)
    ->middleware('throttle:90,1')
    ->name('search.map-features');

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

Route::get('privacy', fn () => view('pages.privacy', ['seo' => Seo::forPrivacy()]))->name('privacy');
Route::get('terms', fn () => view('pages.terms', ['seo' => Seo::forTerms()]))->name('terms');
Route::get('contact', fn () => view('pages.contact', ['seo' => Seo::forContact()]))->name('contact');

Route::post('feedback/editor-upload', FeedbackEditorUploadController::class)
    ->middleware('throttle:feedback-upload')
    ->name('feedback.editor-upload');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function () {
    Route::redirect('slots', '/dashboard', 301);

    Route::get('organizations', function () {
        abort_unless(auth()->user()?->canCreateEvents(), 403, __('ui.organizations.only_event_organizers_can_manage'));

        return view('organizations.index');
    })->name('organizations.index');

    Route::resource('organizations', OrganizationController::class)
        ->except(['show', 'index']);

    Route::get('events/{event}/propose', function (Event $event) {
        $user = auth()->user();
        abort_unless($event->is_public || $user?->canModifyEntity($event), 403);

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

    Route::get('activities/{activity}/participants.pdf', DownloadActivityParticipantsPdfController::class)
        ->name('activities.participants.pdf');
    Route::get('events/{event}/participants.pdf', DownloadEventParticipantsPdfController::class)
        ->name('events.participants.pdf');

    Route::view('activity-proposals', 'activity-proposals.index')->name('activity-proposals.index');
    Route::post('activity-proposals/{proposal}/accept', [ActivityProposalController::class, 'accept'])->name('activity-proposals.accept');
    Route::post('activity-proposals/{proposal}/reject', [ActivityProposalController::class, 'reject'])->name('activity-proposals.reject');

    Route::post('activities/{activity}/join', [ParticipationController::class, 'join'])
        ->middleware(['verified', 'throttle:participation'])
        ->name('activities.join');
    Route::post('activities/{activity}/leave', [ParticipationController::class, 'leave'])
        ->middleware('throttle:participation')
        ->name('activities.leave');
    Route::post('activities/{activity}/join-waitlist', [ParticipationController::class, 'joinWaitlist'])
        ->middleware(['verified', 'throttle:participation'])
        ->name('activities.join-waitlist');
    Route::post('activities/{activity}/leave-waitlist', [ParticipationController::class, 'leaveWaitlist'])
        ->middleware('throttle:participation')
        ->name('activities.leave-waitlist');
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

    Route::view('requests', 'requests.index')->name('requests.index');
    Route::view('notifications', 'notifications.index')->name('notifications.index');
});

// Public event detail route.
// Must be declared after more specific routes like `events/create` and `events/*/edit`,
// otherwise `events/{event}` can consume `create` as the `{event}` slug.
Route::get('events/{event}/calendar.ics', DownloadEventCalendarController::class)
    ->middleware('throttle:60,1')
    ->name('events.calendar.ics');

Route::get('events/{event}', function (Event $event) {
    return view('events.show', compact('event'));
})->name('events.show');

Route::get('event-series/{eventSeries}/calendar.ics', DownloadEventSeriesCalendarController::class)
    ->middleware('throttle:60,1')
    ->name('event-series.calendar.ics');

Route::get('event-series/{eventSeries}', function (EventSeries $eventSeries) {
    return view('event-series.show', compact('eventSeries'));
})->name('event-series.show');

// Public activity detail route.
// Must be declared after more specific routes like `activities/create` and `activities/*/edit`.
Route::get('activities/{activity}/calendar.ics', DownloadActivityCalendarController::class)
    ->middleware('throttle:60,1')
    ->name('activities.calendar.ics');

Route::get('activities/{activity}', function (Activity $activity) {
    abort_unless(
        Activity::query()->whereKey($activity->getKey())->attachedToPublicEvent()->exists(),
        404
    );

    return view('activities.show', compact('activity'));
})->name('activities.show');

require __DIR__.'/auth.php';
