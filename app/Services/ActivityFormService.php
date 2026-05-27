<?php

namespace App\Services;

use App\Actions\Activities\StoreUploadedActivityLogo;
use App\Enums\ActivityLogoSource;
use App\Enums\ActivityProposalStatus;
use App\Jobs\RecalculateTagPopularityJob;
use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\Event;
use App\Models\Place;
use App\Support\RichText;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ActivityFormService
{
    public function __construct(
        private readonly SlotScheduleSyncService $slotScheduleSync,
        private readonly ActivityProposalFlowService $proposalFlow,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function persist(
        ManageActivityForm $form,
        array $validated,
        TagSelectionService $tagSelectionService,
        ActivityHostingModeService $hostingModes,
        LocationResolver $locationResolver,
    ): mixed {
        $validated['description'] = RichText::sanitize($validated['description'] ?? null);
        $validated['requires_approval'] = (bool) ($validated['requires_approval'] ?? false);
        $validated['allows_observers'] = (bool) ($validated['allows_observers'] ?? false);
        $validated['is_host_passive'] = (bool) ($validated['is_host_passive'] ?? false);

        $payload = Arr::except(
            $validated,
            [
                'proposal_event_id',
                'proposal_preferred_start_time',
                'proposal_slot_ids',
                'tag_ids',
                'new_tags',
                'logo_source',
                'selected_tag_media_id',
                'croppedLogo',
            ]
        );

        $tagIds = $tagSelectionService->resolveFinalTagIds(
            $form->tag_ids,
            $form->new_tags
        );

        if ($form->editingActivityId !== null) {
            $activity = Activity::query()->findOrFail($form->editingActivityId);
            $activity->loadMissing('slot.activityTypes');
            $slot = $activity->slot;
            if ($slot !== null) {
                $merged = $activity->replicate();
                $merged->fill($payload);
                $slot->loadMissing('activityTypes');
                if (! $slot->fitsProposalActivity($merged)) {
                    throw ValidationException::withMessages([
                        'max_participants' => [__('ui.activities.activity_no_longer_fits_assigned_slot')],
                    ]);
                }
            }
            $activity->update($payload);
            $this->resolveSelfHostedPlaceSelection($form, $activity, $locationResolver);
            $this->applyHostingModeFromForm($form, $activity, $hostingModes);
            $this->syncActivityTags($activity, $tagIds);
            $this->applyActivityLogoFromForm($form, $activity);
            $proposalCreated = $this->createProposalForActivityIfRequested($form, $activity, $hostingModes);
            $message = $proposalCreated
                ? __('ui.status.activity_updated_with_proposal', ['event' => $proposalCreated->event->name])
                : __('Activity updated.');
        } else {
            $activity = Activity::create($payload);
            $this->resolveSelfHostedPlaceSelection($form, $activity, $locationResolver);
            $this->applyHostingModeFromForm($form, $activity, $hostingModes);
            $this->syncActivityTags($activity, $tagIds);
            $this->applyActivityLogoFromForm($form, $activity);
            $proposalCreated = $this->createProposalForActivityIfRequested($form, $activity, $hostingModes);
            $message = $proposalCreated
                ? __('ui.status.activity_saved_with_proposal', ['event' => $proposalCreated->event->name])
                : __('Activity created.');
        }

        $activity->refresh();
        $this->syncSlotScheduleAfterActivityChange($activity);

        session()->flash('status', $message);

        if ($proposalCreated !== null) {
            return redirect()->route('events.show', $proposalCreated->event);
        }

        if ($form->editingActivityId !== null) {
            return redirect()->route('activities.show', $activity);
        }

        return redirect()->route('search.index');
    }

    private function syncSlotScheduleAfterActivityChange(Activity $activity): void
    {
        $activity->loadMissing('slot.event');
        $slot = $activity->slot;
        if ($slot === null || $slot->event_id === null) {
            return;
        }
        $event = $slot->event;
        $event->load(['slots.activity']);
        $this->slotScheduleSync->syncSlotEndsForEvent($event);
    }

    /**
     * @param  list<int>  $tagIds
     */
    private function syncActivityTags(Activity $activity, array $tagIds): void
    {
        $existingTagIds = array_values(array_unique(
            $activity->tags()->pluck('tags.id')->map(fn (mixed $id): int => (int) $id)->all()
        ));
        $normalizedTagIds = array_values(array_unique(array_map('intval', $tagIds)));

        $sortedExistingTagIds = $existingTagIds;
        $sortedNormalizedTagIds = $normalizedTagIds;
        sort($sortedExistingTagIds);
        sort($sortedNormalizedTagIds);

        $activity->tags()->sync($normalizedTagIds);

        if ($sortedExistingTagIds === $sortedNormalizedTagIds) {
            return;
        }

        $affectedTagIds = array_values(array_unique(array_merge($existingTagIds, $normalizedTagIds)));
        if ($affectedTagIds === []) {
            return;
        }

        RecalculateTagPopularityJob::dispatch($affectedTagIds)->afterCommit();
    }

    private function createProposalForActivityIfRequested(
        ManageActivityForm $form,
        Activity $activity,
        ActivityHostingModeService $hostingModes,
    ): ?ActivityProposal {
        if ($form->proposal_event_id === null || $form->proposal_event_id === 0) {
            return null;
        }

        $hasActiveProposal = ActivityProposal::query()
            ->where('activity_id', $activity->id)
            ->whereIn('status', [ActivityProposalStatus::Pending, ActivityProposalStatus::Accepted])
            ->exists();
        if ($hasActiveProposal) {
            return null;
        }

        $event = Event::findOrFail($form->proposal_event_id);

        $preferred = $form->proposal_preferred_start_time;
        $preferredUtc = $preferred !== null && $preferred !== ''
            ? parse_datetime_to_utc((string) $preferred)?->toDateTimeString()
            : null;

        $proposal = ActivityProposal::create([
            'activity_id' => $activity->id,
            'event_id' => $event->id,
            'created_by' => auth()->id(),
            'preferred_start_time' => $preferredUtc,
            'status' => ActivityProposalStatus::Pending,
        ]);
        $proposal->load(['activity', 'event', 'creator']);

        $hostingModes->markProposedToEvent($activity);

        $this->proposalFlow->notifyHostOfNewProposal($proposal);
        $this->proposalFlow->attachProposedSlotsAndTryAutoAccept($proposal, $event, $activity, $form->proposal_slot_ids);

        return $proposal;
    }

    private function applyHostingModeFromForm(ManageActivityForm $form, Activity $activity, ActivityHostingModeService $hostingModes): void
    {
        if ($activity->hosting_mode === Activity::HOSTING_MODE_SCHEDULED_ON_EVENT) {
            return;
        }

        if ($form->hosting_mode === Activity::HOSTING_MODE_SELF_HOSTED) {
            if ($form->self_hosted_place_id === null || $form->self_hosted_starts_at === null) {
                throw ValidationException::withMessages([
                    'hosting_mode' => [__('ui.activities.self_hosted_requires_place_and_start')],
                ]);
            }
            $hostingModes->setSelfHosted($activity, (int) $form->self_hosted_place_id, (string) $form->self_hosted_starts_at);

            return;
        }

        if ($form->hosting_mode === Activity::HOSTING_MODE_PROPOSED_TO_EVENT) {
            if ($activity->hosting_mode === Activity::HOSTING_MODE_SELF_HOSTED) {
                $hostingModes->moveSelfHostedToProposed($activity);
            } else {
                $hostingModes->markProposedToEvent($activity);
            }

            return;
        }

        $hostingModes->setDraft($activity);
    }

    private function resolveSelfHostedPlaceSelection(ManageActivityForm $form, Activity $activity, LocationResolver $locationResolver): void
    {
        if ($form->hosting_mode !== Activity::HOSTING_MODE_SELF_HOSTED) {
            return;
        }

        if ($form->self_hosted_venue_place_id === null && $form->new_places !== []) {
            $form->self_hosted_place_id = null;
            foreach ($form->new_places as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['name'] ?? ''));
                $lat = $row['latitude'] ?? null;
                $lng = $row['longitude'] ?? null;
                if ($name === '' || $lat === null || $lat === '' || $lng === null || $lng === '') {
                    continue;
                }

                $resolved = $locationResolver->resolvePlaceRow($row);
                $newVenue = Place::create([
                    'name' => $name,
                    'address' => trim((string) ($row['address'] ?? '')) ?: null,
                    'type' => 'venue',
                    'city_id' => $resolved['city_id'],
                    'country_id' => $resolved['country_id'],
                    'latitude' => (float) $lat,
                    'longitude' => (float) $lng,
                    'is_online' => false,
                ]);
                $form->self_hosted_venue_place_id = $newVenue->id;
                break;
            }
        }

        if ($form->self_hosted_venue_place_id === null) {
            return;
        }

        $venue = Place::query()
            ->whereKey($form->self_hosted_venue_place_id)
            ->where('type', 'venue')
            ->first();
        if ($venue === null) {
            return;
        }

        if ($form->self_hosted_room_name !== null) {
            $room = Place::query()
                ->where('type', 'room')
                ->where('parent_id', $venue->id)
                ->whereRaw('LOWER(name) = LOWER(?)', [$form->self_hosted_room_name])
                ->first();
            if ($room === null) {
                $room = Place::create([
                    'name' => $form->self_hosted_room_name,
                    'type' => 'room',
                    'parent_id' => $venue->id,
                    'city_id' => $venue->city_id,
                    'country_id' => $venue->country_id,
                    'is_online' => false,
                ]);
            }
            $form->self_hosted_place_id = $room->id;

            return;
        }

        $form->self_hosted_place_id = $venue->id;
    }

    private function applyActivityLogoFromForm(ManageActivityForm $form, Activity $activity): void
    {
        $source = ActivityLogoSource::tryFrom((string) ($form->logo_source ?? ''));

        if ($source === ActivityLogoSource::Tag) {
            $this->deleteActivityLogoFileIfPresent($activity);
            $activity->logo_source = ActivityLogoSource::Tag;
            $activity->tag_media_id = $form->selected_tag_media_id;
            $activity->logo_path = null;
        } elseif ($source === ActivityLogoSource::Upload) {
            $activity->logo_source = ActivityLogoSource::Upload;
            $activity->tag_media_id = null;

            if ($form->croppedLogo !== null) {
                $activity->logo_path = app(StoreUploadedActivityLogo::class)($activity, $form->croppedLogo);
            }
        } else {
            $this->deleteActivityLogoFileIfPresent($activity);
            $activity->logo_source = null;
            $activity->tag_media_id = null;
            $activity->logo_path = null;
        }

        $activity->save();
        $form->reset('croppedLogo');
    }

    private function deleteActivityLogoFileIfPresent(Activity $activity): void
    {
        $path = $this->activityLogoStoragePath($activity);
        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function activityLogoStoragePath(Activity $activity): ?string
    {
        if (filled($activity->logo_path)) {
            return (string) $activity->logo_path;
        }

        $canonical = 'activity-logos/'.$activity->id.'.webp';

        return Storage::disk('public')->exists($canonical) ? $canonical : null;
    }
}
