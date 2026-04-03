<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Activities') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex justify-end">
                <x-button :link="route('activities.create')" class="btn-primary">{{ __('Add activity') }}</x-button>
            </div>

            <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Host') }}</th>
                                <th class="w-0"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($activities as $activity)
                                <tr>
                                    <td>
                                        <a href="{{ route('activities.show', $activity) }}" class="link link-primary font-medium">
                                            {{ $activity->name }}
                                        </a>
                                    </td>
                                    <td class="opacity-80">{{ strtoupper($activity->type) }}</td>
                                    <td class="opacity-80">{{ $activity->host?->nickname ?? $activity->host?->email ?? '—' }}</td>
                                    <td class="text-end">
                                        @if ($activity->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                                            <a href="{{ route('activities.edit', $activity) }}" class="link link-primary me-3">
                                                {{ __('Edit') }}
                                            </a>

                                            <form action="{{ route('activities.destroy', $activity) }}" method="POST" class="inline">
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
                                    <td colspan="4" class="text-center opacity-70">
                                        {{ __('No activities yet.') }}
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
