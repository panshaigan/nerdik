@php
    $enforceFuture = (bool) ($enforceFutureDates ?? false);
    $eventStartsAtMin = $enforceFuture ? format_in_user_tz(now(), 'Y-m-d\TH:i') : null;
    $eventEndsAtMin = ($starts_at ?? '') !== ''
        ? $starts_at
        : ($enforceFuture ? format_in_user_tz(now(), 'Y-m-d\TH:i') : null);
@endphp
<div class="grid grid-cols-2 gap-4">
    <div class="relative">
        <x-input
            wire:model.live.debounce.300ms="name"
            label="{{ __('Name') }}"
            placeholder="{{ __('Name') }}"
            type="text"
            error-field="name"
            required
            autocomplete="off"
            data-event-name-input
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="event-name-suggestions-popup"
            icon="o-bookmark"
            inline
        />
        <div id="event-name-suggestions-popup"
             class="absolute inset-x-0 top-full z-20 mt-1 hidden max-h-56 w-full min-w-0 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
             data-event-name-popup
             wire:ignore
             role="listbox">
        </div>
    </div>

    <div class="relative">
        <input type="hidden" wire:model="organization_id" data-event-org-id />
        <x-input
            wire:model.live.debounce.300ms="organization_name"
            label="{{ __('Organization') }}"
            placeholder="{{ __('Organization (optional)') }}"
            type="text"
            error-field="organization_name"
            autocomplete="off"
            data-event-org-input
            aria-autocomplete="list"
            aria-expanded="false"
            aria-controls="event-org-suggestions-popup"
            icon="o-building-office-2"
            inline
        />
        <div id="event-org-suggestions-popup"
             class="absolute inset-x-0 top-full z-20 mt-1 hidden max-h-56 w-full min-w-0 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
             data-event-org-popup
             wire:ignore
             role="listbox"></div>
        <x-field-error :messages="$errors->get('organization_id')" class="mt-2" />
        <x-field-error :messages="$errors->get('organization_name')" class="mt-2" />
    </div>

    <div class="">
        <x-input
            wire:model="starts_at"
            label="{{ __('Starts at') }}"
            type="datetime-local"
            :step="$datetimeMinuteStepSeconds"
            :min="$eventStartsAtMin"
            error-field="starts_at"
            required
            data-event-start-at
            data-enforce-future="{{ $enforceFuture ? '1' : '0' }}"
            class="w-full"
            inline
        />
    </div>

    <div class="">
        <x-input
            wire:model="ends_at"
            label="{{ __('Ends at') }}"
            type="datetime-local"
            :step="$datetimeMinuteStepSeconds"
            :min="$eventEndsAtMin"
            error-field="ends_at"
            required
            data-event-ends-at
            class="w-full"
            inline
        />
    </div>
</div>

<div class="mt-4">
    <x-editor
        wire:model="description"
        :gpl-license="true"
        inline
    />
    <x-field-error :messages="$errors->get('description')" class="mt-2" />
</div>
