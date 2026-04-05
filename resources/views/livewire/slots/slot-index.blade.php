<div class="py-12">
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="alert" class="alert alert-success mb-4 text-sm">{{ session('status') }}</div>
        @endif

        <div class="mb-4 flex flex-wrap justify-between gap-2">
            <x-button :link="route('slots.create')" class="btn-primary">{{ __('Add slot') }}</x-button>

            <x-button :link="route('slots.create', ['mode' => 'mass'])" class="btn-outline">{{ __('Mass create') }}</x-button>
        </div>

        <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Event') }}</th>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Start') }}</th>
                            <th>{{ __('Place') }}</th>
                            <th class="w-0"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($slots as $slot)
                            <tr wire:key="slot-row-{{ $slot->id }}">
                                <td class="opacity-90">
                                    {{ $slot->event->name }} · {{ format_in_user_tz($slot->event->starts_at, 'Y-m-d') }}
                                </td>
                                <td class="opacity-80">{{ $slot->name }}</td>
                                <td class="opacity-80">{{ $slot->starts_at ? format_in_user_tz($slot->starts_at) : '—' }}</td>
                                <td class="opacity-80">{{ $slot->place?->venueRoomLabel() ?? '—' }}</td>
                                <td class="text-end">
                                    @canModifyEntity($slot)
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-sm text-primary me-3"
                                            onclick="window.openSlotEditModal?.({{ $slot->id }})"
                                        >
                                            {{ __('Edit') }}
                                        </button>

                                        <x-button
                                            type="button"
                                            class="btn-ghost btn-sm text-error"
                                            wire:click="deleteSlot({{ $slot->id }})"
                                            wire:confirm="{{ __('Are you sure?') }}"
                                        >
                                            {{ __('Delete') }}
                                        </x-button>
                                    @endcanModifyEntity
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center opacity-70">
                                    {{ __('No slots yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('slots.partials.edit-modal-shell')
    </div>
</div>
