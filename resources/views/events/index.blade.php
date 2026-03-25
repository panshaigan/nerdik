<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Events') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-end mb-4">
                <a href="{{ route('events.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('Add event') }}
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
                                {{ __('When') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Organization') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Public') }}
                            </th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($events as $event)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">
                                    <a href="{{ route('events.show', $event) }}" class="text-indigo-600 hover:text-indigo-900">{{ $event->name }}</a>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500 whitespace-nowrap">
                                    {{ format_in_user_tz($event->starts_at, 'Y-m-d H:i') }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ $event->organization?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ $event->is_public ? __('Yes') : __('No') }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <a href="{{ route('events.edit', $event) }}"
                                       class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        {{ __('Edit') }}
                                    </a>

                                    <form action="{{ route('events.destroy', $event) }}" method="POST" class="inline">
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
                                <td colspan="5" class="px-4 py-4 text-sm text-gray-500 text-center">
                                    {{ __('No events yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
