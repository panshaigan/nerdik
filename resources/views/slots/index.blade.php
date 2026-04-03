<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Slots') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
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
                                <tr>
                                    <td class="opacity-90">
                                        {{ $slot->event->name }} · {{ format_in_user_tz($slot->event->starts_at, 'Y-m-d') }}
                                    </td>
                                    <td class="opacity-80">{{ $slot->name }}</td>
                                    <td class="opacity-80">{{ $slot->starts_at ? format_in_user_tz($slot->starts_at) : '—' }}</td>
                                    <td class="opacity-80">{{ $slot->place?->venueRoomLabel() ?? '—' }}</td>
                                    <td class="text-end">
                                        @if ($slot->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-sm text-primary me-3"
                                                onclick="window.openSlotEditModal?.({{ $slot->id }})"
                                            >
                                                {{ __('Edit') }}
                                            </button>

                                            <form action="{{ route('slots.destroy', $slot) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    onclick="return confirm('{{ __('Are you sure?') }}')"
                                                    class="btn btn-ghost btn-sm text-error"
                                                >
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        @endif
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
</x-app-layout>
