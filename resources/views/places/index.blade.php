<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Places') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex justify-end">
                <x-button :link="route('places.create')" class="btn-primary">{{ __('Add place') }}</x-button>
            </div>

            <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Type') }}</th>
                                <th>{{ __('Parent') }}</th>
                                <th class="w-0"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($places as $place)
                                <tr>
                                    <td class="font-medium opacity-90">{{ $place->name }}</td>
                                    <td class="opacity-80">{{ ucfirst($place->type) }}</td>
                                    <td class="opacity-80">{{ $place->parent?->name ?? '—' }}</td>
                                    <td class="text-end">
                                        @if ($place->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                                            <a href="{{ route('places.edit', $place) }}" class="link link-primary me-3">
                                                {{ __('Edit') }}
                                            </a>

                                            <form action="{{ route('places.destroy', $place) }}" method="POST" class="inline">
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
                                        {{ __('No places yet.') }}
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
