<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Slots') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-between mb-4">
                <a href="{{ route('slots.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500">
                    {{ __('Add slot') }}
                </a>

                <a href="{{ route('slots.create', ['mode' => 'mass']) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200">
                    {{ __('Mass create') }}
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
                                {{ __('Place') }}
                            </th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($slots as $slot)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">
                                    {{ $slot->event->name }} · {{ format_in_user_tz($slot->event->starts_at, 'Y-m-d') }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ $slot->name }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ $slot->starts_at ? format_in_user_tz($slot->starts_at) : '—' }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ $slot->place?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    <a href="{{ route('slots.edit', $slot) }}"
                                       class="text-indigo-600 hover:text-indigo-900 mr-3">
                                        {{ __('Edit') }}
                                    </a>

                                    <form action="{{ route('slots.destroy', $slot) }}" method="POST" class="inline">
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
                                    {{ __('No slots yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

