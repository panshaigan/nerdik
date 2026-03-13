<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\ActivityProposalController;
use App\Http\Controllers\BrowseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\EventInstanceController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('browse/events', [BrowseController::class, 'events'])->name('browse.events');
Route::get('browse/activities', [BrowseController::class, 'activities'])->name('browse.activities');

Route::get('dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth'])->group(function () {
    Route::resource('organizations', OrganizationController::class)
        ->except(['show']);

    Route::resource('events', EventController::class)
        ->except(['show']);

    Route::resource('event-instances', EventInstanceController::class)
        ->except(['show']);

    Route::resource('slots', SlotController::class)
        ->except(['show']);

    Route::resource('places', PlaceController::class)
        ->except(['show']);

    Route::resource('tags', TagController::class)
        ->except(['show']);

    Route::resource('activities', ActivityController::class);

    Route::resource('activity-proposals', ActivityProposalController::class)
        ->only(['index', 'store']);

    Route::post('activities/{activity}/join', [ParticipationController::class, 'join'])->name('activities.join');
    Route::post('activities/{activity}/leave', [ParticipationController::class, 'leave'])->name('activities.leave');
    Route::post('activities/{activity}/join-waitlist', [ParticipationController::class, 'joinWaitlist'])->name('activities.join-waitlist');
    Route::post('activities/{activity}/leave-waitlist', [ParticipationController::class, 'leaveWaitlist'])->name('activities.leave-waitlist');
    Route::post('activity-participants/{participant}/mark-absent', [ParticipationController::class, 'markAbsent'])->name('activity-participants.mark-absent');
});

require __DIR__.'/auth.php';
