<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Event instances') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-end mb-4">
                <a href="{{ route('event-instances.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('Add event instance') }}
                </a>
            </div>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Event') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Name') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Start') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('End') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Owner') }}
                            </th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($instances as $instance)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">
                                    {{ $instance->event->name }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    <a href="{{ route('event-instances.show', $instance) }}" class="text-indigo-600 hover:text-indigo-900">{{ $instance->name ?? format_in_user_tz($instance->starts_at, 'Y-m-d') }}</a>
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ format_in_user_tz($instance->starts_at) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ format_in_user_tz($instance->ends_at) }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ $instance->event->creator?->nickname ?? $instance->event->creator?->email ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <a href="{{ route('event-instances.edit', $instance) }}"
                                       class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        {{ __('Edit') }}
                                    </a>

                                    <form action="{{ route('event-instances.destroy', $instance) }}" method="POST" class="inline">
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
                                <td colspan="6" class="px-4 py-4 text-sm text-gray-500 text-center">
                                    {{ __('No event instances yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

