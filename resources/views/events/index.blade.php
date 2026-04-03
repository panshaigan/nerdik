<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Events') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex justify-end">
                <x-button :link="route('events.create')" class="btn-primary">{{ __('Add event') }}</x-button>
            </div>

            <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('When') }}</th>
                                <th>{{ __('Organization') }}</th>
                                <th>{{ __('Public') }}</th>
                                <th class="w-0"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($events as $event)
                                <tr>
                                    <td>
                                        <a href="{{ route('events.show', $event) }}" class="link link-primary font-medium">{{ $event->name }}</a>
                                    </td>
                                    <td class="whitespace-nowrap opacity-80">
                                        {{ format_in_user_tz($event->starts_at, 'Y-m-d H:i') }}
                                    </td>
                                    <td class="opacity-80">{{ $event->organization?->name ?? '—' }}</td>
                                    <td class="opacity-80">{{ $event->is_public ? __('Yes') : __('No') }}</td>
                                    <td class="text-end">
                                        @if ($event->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                                            <a href="{{ route('events.edit', $event) }}" class="link link-primary me-3">
                                                {{ __('Edit') }}
                                            </a>
                                        @endif

                                        @if ($event->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                                            <form action="{{ route('events.copy', $event) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="btn btn-ghost btn-sm me-3">
                                                    {{ __('Copy') }}
                                                </button>
                                            </form>
                                        @endif

                                        @if ($event->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                                            <form action="{{ route('events.destroy', $event) }}" method="POST" class="inline">
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
                                        {{ __('No events yet.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
