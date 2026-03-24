<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Browse activities') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="GET" class="mb-6 flex flex-wrap gap-4 items-end">
                <div>
                    <label for="q" class="block text-sm font-medium text-gray-700">{{ __('Search') }}</label>
                    <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('Activity name…') }}" class="mt-1 block rounded-md border-gray-300 w-48">
                </div>
                <div>
                    <label for="from_date" class="block text-sm font-medium text-gray-700">{{ __('From date') }}</label>
                    <input type="date" id="from_date" name="from_date" value="{{ request('from_date') }}" class="mt-1 block rounded-md border-gray-300">
                </div>
                <div>
                    <label for="to_date" class="block text-sm font-medium text-gray-700">{{ __('To date') }}</label>
                    <input type="date" id="to_date" name="to_date" value="{{ request('to_date') }}" class="mt-1 block rounded-md border-gray-300">
                </div>
                @if ($places->isNotEmpty())
                    <div>
                        <label for="place_id" class="block text-sm font-medium text-gray-700">{{ __('Place') }}</label>
                        <select id="place_id" name="place_id" class="mt-1 block rounded-md border-gray-300">
                            <option value="">{{ __('Any') }}</option>
                            @foreach ($places as $place)
                                <option value="{{ $place->id }}" @selected(request('place_id') == $place->id)>{{ $place->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
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
                @if (request()->hasAny(['q', 'from_date', 'to_date', 'place_id', 'tag_id']))
                    <a href="{{ route('browse.activities') }}" class="text-sm text-gray-600 hover:text-gray-900">{{ __('Clear') }}</a>
                @endif
            </form>

            <div class="bg-white shadow sm:rounded-lg overflow-hidden">
                <ul class="divide-y divide-gray-200">
                    @forelse ($activities as $activity)
                        <li class="p-4 flex items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('activities.show', $activity) }}" class="font-medium text-indigo-600 hover:text-indigo-900">
                                    {{ $activity->name }}
                                </a>
                                <span class="text-gray-500 text-sm"> · {{ ucfirst($activity->type) }}</span>
                                @if ($activity->slot && $activity->slot->eventInstance)
                                    <span class="text-gray-500 text-sm"> · {{ $activity->slot->eventInstance->event->name }}</span>
                                    @if ($activity->slot->starts_at)
                                        <span class="text-gray-500 text-sm"> · {{ format_in_user_tz($activity->slot->starts_at) }}</span>
                                    @endif
                                @endif
                                <p class="text-sm text-gray-500 mt-1">
                                    {{ __('Host') }}: {{ $activity->host?->nickname ?? $activity->host?->email ?? '—' }}
                                    · {{ $activity->participants()->count() }}{{ $activity->max_participants ? '/'.$activity->max_participants : '' }} {{ __('participants') }}
                                </p>
                                @include('tags.partials.inline', ['tags' => $activity->tags])
                            </div>
                            @auth
                                <div class="shrink-0">
                                    @if (in_array($activity->id, $wishlistActivityIds ?? []))
                                        <form action="{{ route('wishlist.activities.remove', $activity) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-amber-600 hover:text-amber-800" title="{{ __('Remove from wishlist') }}">★ {{ __('Remove') }}</button>
                                        </form>
                                    @else
                                        <form action="{{ route('wishlist.activities.add', $activity) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-sm text-gray-500 hover:text-amber-600" title="{{ __('Add to wishlist') }}">☆ {{ __('Add to wishlist') }}</button>
                                        </form>
                                    @endif
                                </div>
                            @endauth
                        </li>
                    @empty
                        <li class="p-6 text-center text-gray-500">{{ __('No activities found.') }}</li>
                    @endforelse
                </ul>
                @if ($activities->hasPages())
                    <div class="p-4 border-t">{{ $activities->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
