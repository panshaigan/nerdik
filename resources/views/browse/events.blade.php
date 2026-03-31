<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Browse events') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <form method="GET" class="card border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="q" class="block text-sm font-medium opacity-80">{{ __('Search') }}</label>
                        <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('Name or description…') }}" class="input input-bordered mt-1 w-64">
                    </div>
                    @if ($tags->isNotEmpty())
                        <div>
                            <label for="tag_id" class="block text-sm font-medium opacity-80">{{ __('Tag') }}</label>
                            <select id="tag_id" name="tag_id" class="select select-bordered mt-1">
                                <option value="">{{ __('Any') }}</option>
                                @foreach ($tags as $tag)
                                    <option value="{{ $tag->id }}" @selected(request('tag_id') == $tag->id)>
                                        {{ $tag->translations->firstWhere('locale', app()->getLocale())?->label ?? $tag->slug }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
                    @if (request()->hasAny(['q', 'tag_id']))
                        <a href="{{ route('browse.events') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
                    @endif
                </div>
            </form>

            @auth
                <div class="mb-1 flex justify-end">
                    <a href="{{ route('events.create') }}"
                       class="btn btn-primary">
                        {{ __('Create event') }}
                    </a>
                </div>
            @endauth

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($events as $event)
                    <x-cards.event-card :event="$event" :wishlist-event-ids="$wishlistEventIds ?? []" />
                @empty
                    <div class="col-span-full">
                        <div class="rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                            {{ __('No public events found.') }}
                            @auth
                                <div class="mt-3">
                                    <a href="{{ route('events.create') }}" class="link link-primary">{{ __('Create one') }}</a>
                                </div>
                            @endauth
                        </div>
                    </div>
                @endforelse
            </div>

            @if ($events->hasPages())
                <div class="mt-6 rounded-xl border border-base-300 bg-base-100 p-4">{{ $events->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
