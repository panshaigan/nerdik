<form
    method="POST"
    action="{{ route('slots.update', $slot) }}"
    data-slot-edit-form
    class="space-y-0"
>
    @csrf
    @method('PUT')
    <input type="hidden" name="redirect_to_event_slug" value="{{ $slot->event->slug }}" />

    @include('slots.form', [
        'submitLabel' => __('ui.common.save'),
        'embeddedInModal' => true,
        'lockedEvent' => $lockedEvent,
        'slot' => $slot,
        'events' => $events,
        'places' => $places,
        'tags' => $tags,
        'slotNameSuggestions' => $slotNameSuggestions ?? [],
    ])
</form>
