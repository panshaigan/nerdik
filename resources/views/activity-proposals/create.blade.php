<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('ui.proposals.propose_activity') }} · {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                <p class="mb-4 text-sm text-base-content/80">
                    {{ format_in_user_tz($event->starts_at, 'Y-m-d H:i') }} – {{ format_in_user_tz($event->ends_at, 'Y-m-d H:i') }}
                </p>

                <form method="POST" action="{{ route('activity-proposals.store') }}">
                    @csrf
                    <input type="hidden" name="event_id" value="{{ $event->id }}">

                    <div class="space-y-4">
                        <div>
                            <fieldset class="fieldset py-0">
                                <legend class="fieldset-legend mb-0.5">{{ __('ui.proposals.your_activity') }}</legend>
                                <select id="activity_id" name="activity_id" class="select select-bordered w-full" required>
                                    <option value="">{{ __('ui.proposals.choose_activity') }}</option>
                                    @foreach ($myActivities as $activity)
                                        <option value="{{ $activity->id }}" @selected(old('activity_id') == $activity->id)>
                                            {{ $activity->name }} ({{ ucfirst($activity->type) }})
                                        </option>
                                    @endforeach
                                </select>
                            </fieldset>
                            <x-field-error :messages="$errors->get('activity_id')" class="mt-2" />
                            @if ($myActivities->isEmpty())
                                <p class="mt-1 text-sm text-warning">
                                    {{ __('ui.proposals.no_activities_yet') }}
                                    <a href="{{ route('activities.create') }}" class="link link-primary">{{ __('ui.proposals.create_one') }}</a>
                                </p>
                            @else
                                <p class="mt-1 text-sm text-base-content/60">
                                    <a href="{{ route('activities.create') }}" class="link link-primary">{{ __('ui.proposals.create_new_activity') }}</a>
                                </p>
                            @endif
                        </div>

                        <div>
                            <p class="fieldset-legend font-medium text-base-content">{{ __('ui.proposals.preferred_slots_optional') }}</p>
                            <p class="mb-2 text-sm text-base-content/60">{{ __('ui.proposals.preferred_slots_help') }}</p>
                            <div class="space-y-2">
                                @foreach ($event->slots as $slot)
                                    <label class="flex cursor-pointer items-center gap-2">
                                        <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}" @checked(in_array((string) $slot->id, (array) old('slot_ids', []))) class="checkbox checkbox-sm" />
                                        <span class="text-sm">{{ $slot->name }}</span>
                                        @if ($slot->starts_at)
                                            <span class="text-xs text-base-content/50">{{ format_in_user_tz($slot->starts_at, 'H:i') }}</span>
                                        @endif
                                        @if ($slot->activity_id)
                                            <span class="text-xs text-base-content/40">({{ __('ui.proposals.taken') }})</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            <x-field-error :messages="$errors->get('slot_ids')" class="mt-2" />
                        </div>

                        <div>
                            <x-input
                                label="{{ __('ui.proposals.preferred_start_time_optional') }}"
                                name="preferred_start_time"
                                type="datetime-local"
                                value="{{ old('preferred_start_time') }}"
                                error-field="preferred_start_time"
                            />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('events.show', $event) }}" class="btn btn-outline">{{ __('ui.common.cancel') }}</a>
                        <x-button class="btn-primary" type="submit">{{ __('ui.proposals.submit_proposal') }}</x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
