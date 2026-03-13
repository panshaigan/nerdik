<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Activities') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-end mb-4">
                <a href="{{ route('activities.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('Add activity') }}
                </a>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Name') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Type') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Host') }}
                            </th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($activities as $activity)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">
                                    <a href="{{ route('activities.show', $activity) }}" class="text-indigo-600 hover:text-indigo-900">
                                        {{ $activity->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ strtoupper($activity->type) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ $activity->host?->nickname ?? $activity->host?->email ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <a href="{{ route('activities.edit', $activity) }}"
                                       class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        {{ __('Edit') }}
                                    </a>

                                    <form action="{{ route('activities.destroy', $activity) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                onclick="return confirm('{{ __('Are you sure?') }}')"
                                                class="text-red-600 hover:text-red-900">
                                            {{ __('Delete') }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-4 text-sm text-gray-500 text-center">
                                    {{ __('No activities yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

