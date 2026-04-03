<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Organizations') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="mb-4 flex justify-end">
                <x-button :link="route('organizations.create')" class="btn-primary">{{ __('Add organization') }}</x-button>
            </div>

            <div class="overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('Name') }}</th>
                                <th>{{ __('Slug') }}</th>
                                <th>{{ __('Owner') }}</th>
                                <th class="w-0"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($organizations as $organization)
                                <tr>
                                    <td class="font-medium opacity-90">{{ $organization->name }}</td>
                                    <td class="opacity-80">{{ $organization->slug }}</td>
                                    <td class="opacity-80">{{ $organization->creator?->nickname ?? $organization->creator?->email ?? '—' }}</td>
                                    <td class="text-end">
                                        @if ($organization->created_by === auth()->id() || (auth()->user()->is_admin ?? false))
                                            <a href="{{ route('organizations.edit', $organization) }}" class="link link-primary me-3">
                                                {{ __('Edit') }}
                                            </a>

                                            <form action="{{ route('organizations.destroy', $organization) }}" method="POST" class="inline">
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
                                        {{ __('No organizations yet.') }}
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
