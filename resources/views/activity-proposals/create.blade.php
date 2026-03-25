<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Propose an activity') }} · {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <p class="text-sm text-gray-600 mb-4">
                    {{ format_in_user_tz($event->starts_at, 'Y-m-d H:i') }} – {{ format_in_user_tz($event->ends_at, 'Y-m-d H:i') }}
                </p>

                <form method="POST" action="{{ route('activity-proposals.store') }}">
                    @csrf
                    <input type="hidden" name="event_id" value="{{ $event->id }}">

                    <div class="space-y-4">
                        <div>
                            <x-input-label for="activity_id" :value="__('Your activity')" />
                            <select id="activity_id" name="activity_id" class="mt-1 block w-full rounded-md border-gray-300" required>
                                <option value="">{{ __('Choose an activity') }}</option>
                                @foreach ($myActivities as $activity)
                                    <option value="{{ $activity->id }}" @selected(old('activity_id') == $activity->id)>
                                        {{ $activity->name }} ({{ ucfirst($activity->type) }})
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('activity_id')" class="mt-2" />
                            @if ($myActivities->isEmpty())
                                <p class="mt-1 text-sm text-amber-600">
                                    {{ __('You have no activities yet.') }}
                                    <a href="{{ route('activities.create') }}" class="underline">{{ __('Create one') }}</a>
                                </p>
                            @else
                                <p class="mt-1 text-sm text-gray-500">
                                    <a href="{{ route('activities.create') }}" class="text-indigo-600 hover:text-indigo-900">{{ __('Create a new activity') }}</a>
                                </p>
                            @endif
                        </div>

                        <div>
                            <x-input-label :value="__('Preferred slots (optional)')" />
                            <p class="text-sm text-gray-500 mb-2">{{ __('Select slots you would like to run this activity in. Leave empty to let the organizer decide.') }}</p>
                            <div class="space-y-2">
                                @foreach ($event->slots as $slot)
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="slot_ids[]" value="{{ $slot->id }}" @checked(in_array((string) $slot->id, (array) old('slot_ids', []))) class="rounded border-gray-300">
                                        <span class="text-sm">{{ $slot->name }}</span>
                                        @if ($slot->starts_at)
                                            <span class="text-gray-500 text-xs">{{ format_in_user_tz($slot->starts_at, 'H:i') }}</span>
                                        @endif
                                        @if ($slot->activity_id)
                                            <span class="text-gray-400 text-xs">({{ __('taken') }})</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('slot_ids')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="preferred_start_time" :value="__('Preferred start time (optional)')" />
                            <x-text-input id="preferred_start_time" name="preferred_start_time" type="datetime-local" class="mt-1 block w-full"
                                          value="{{ old('preferred_start_time') }}" />
                            <x-input-error :messages="$errors->get('preferred_start_time')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('events.show', $event) }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Cancel') }}</a>
                        <x-primary-button type="submit">{{ __('Submit proposal') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
