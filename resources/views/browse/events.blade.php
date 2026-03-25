<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Browse events') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="GET" class="mb-6 flex flex-wrap gap-4 items-end">
                <div>
                    <label for="q" class="block text-sm font-medium text-gray-700">{{ __('Search') }}</label>
                    <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('Name or description…') }}" class="mt-1 block rounded-md border-gray-300 w-64">
                </div>
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
                <button type="submit" class="px-4 py-2 bg-gray-200 rounded-md text-sm">{{ __('Search') }}</button>
                @if (request()->hasAny(['q', 'tag_id']))
                    <a href="{{ route('browse.events') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <ul class="divide-y divide-gray-200">
                    @forelse ($events as $event)
                        <li class="p-4 flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
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
                                @if ($event->creator)
                                    <span class="text-gray-500 text-sm"> · {{ __('by') }} {{ $event->creator->nickname ?? $event->creator->email }}</span>
                                @endif
                                <p class="text-sm text-gray-500 mt-1">{{ format_in_user_tz($event->starts_at) }} – {{ format_in_user_tz($event->ends_at) }}</p>
                                <p class="text-sm text-gray-500 mt-1">{{ Str::limit($event->desc, 120) }}</p>
                                @include('tags.partials.inline', ['tags' => $event->tags])
                                <span class="inline-flex items-center gap-2 mt-2">
                                    <a href="{{ route('events.show', $event) }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                                        {{ __('View event & propose activity') }} →
                                    </a>
                                    <button type="button" x-data="{ copied: false }" x-on:click="navigator.clipboard.writeText('{{ route('events.show', $event) }}'); copied = true; setTimeout(() => copied = false, 2000)" class="text-xs text-gray-400 hover:text-gray-600" title="{{ __('Copy link') }}"><span x-show="!copied">{{ __('Share') }}</span><span x-show="copied" x-cloak>{{ __('Copied!') }}</span></button>
                                </span>
                            </div>
                            @auth
                                <div class="shrink-0">
                                    @if (in_array($event->id, $wishlistEventIds ?? []))
                                        <form action="{{ route('wishlist.events.remove', $event) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-amber-600 hover:text-amber-800" title="{{ __('Remove from wishlist') }}">★ {{ __('Remove') }}</button>
                                        </form>
                                    @else
                                        <form action="{{ route('wishlist.events.add', $event) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-sm text-gray-500 hover:text-amber-600" title="{{ __('Add to wishlist') }}">☆ {{ __('Add to wishlist') }}</button>
                                        </form>
                                    @endif
                                </div>
                            @endauth
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
