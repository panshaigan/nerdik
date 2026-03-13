<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Browse events') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="GET" class="mb-6 flex flex-wrap gap-4 items-end">
                @if ($tags->isNotEmpty())
                    <div>
                        <label for="tag_id" class="block text-sm font-medium text-gray-700">{{ __('Tag') }}</label>
                        <select id="tag_id" name="tag_id" class="mt-1 block rounded-md border-gray-300">
                            <option value="">{{ __('Any') }}</option>
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}" @selected(request('tag_id') == $tag->id)>
                                    {{ $tag->translations->firstWhere('locale', app()->getLocale())?->label ?? $tag->slug }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <button type="submit" class="px-4 py-2 bg-gray-200 rounded-md text-sm">{{ __('Filter') }}</button>
            </form>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <ul class="divide-y divide-gray-200">
                    @forelse ($events as $event)
                        <li class="p-4">
                            @auth
                                @if ($event->created_by === auth()->id())
                                    <a href="{{ route('events.edit', $event) }}" class="font-medium text-indigo-600 hover:text-indigo-900">{{ $event->name }}</a>
                                @else
                                    <span class="font-medium text-gray-900">{{ $event->name }}</span>
                                @endif
                            @else
                                <span class="font-medium text-gray-900">{{ $event->name }}</span>
                            @endauth
                            @if ($event->organization)
                                <span class="text-gray-500 text-sm"> · {{ $event->organization->name }}</span>
                            @endif
                            <p class="text-sm text-gray-500 mt-1">{{ Str::limit($event->desc, 120) }}</p>
                        </li>
                    @empty
                        <li class="p-6 text-center text-gray-500">{{ __('No public events found.') }}</li>
                    @endforelse
                </ul>
                @if ($events->hasPages())
                    <div class="p-4 border-t">{{ $events->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
