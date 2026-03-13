<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Activity proposals') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Activity') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Event instance') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Proposer') }}
                            </th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                {{ __('Status') }}
                            </th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($proposals as $proposal)
                            <tr>
                                <td class="px-4 py-2 text-sm text-gray-900">
                                    {{ $proposal->activity->name }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ $proposal->eventInstance->event->name }}
                                    –
                                    {{ $proposal->eventInstance->name ?? $proposal->eventInstance->starts_at->format('Y-m-d') }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ $proposal->creator->nickname ?? $proposal->creator->email }}
                                </td>
                                <td class="px-4 py-2 text-sm text-gray-500">
                                    {{ ucfirst($proposal->status) }}
                                </td>
                                <td class="px-4 py-2 text-right text-sm">
                                    @if ($proposal->acceptedSlot)
                                        <span class="text-xs text-green-700">
                                            {{ __('Accepted in slot') }}: {{ $proposal->acceptedSlot->name }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-4 text-sm text-gray-500 text-center">
                                    {{ __('No proposals yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>

