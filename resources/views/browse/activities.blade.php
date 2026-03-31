<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Browse activities') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <form method="GET" class="card border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="flex flex-wrap items-end gap-4">
                    <div>
                        <label for="q" class="block text-sm font-medium opacity-80">{{ __('Search') }}</label>
                        <input type="text" id="q" name="q" value="{{ request('q') }}" placeholder="{{ __('Activity name…') }}" class="input input-bordered mt-1 w-64">
                    </div>
                    <div>
                        <label for="from_date" class="block text-sm font-medium opacity-80">{{ __('From') }}</label>
                        <input type="date" id="from_date" name="from_date" value="{{ request('from_date') }}" class="input input-bordered mt-1">
                    </div>
                    <div>
                        <label for="to_date" class="block text-sm font-medium opacity-80">{{ __('To') }}</label>
                        <input type="date" id="to_date" name="to_date" value="{{ request('to_date') }}" class="input input-bordered mt-1">
                    </div>
                    <div>
                        <label for="place_id" class="block text-sm font-medium opacity-80">{{ __('Place') }}</label>
                        <select id="place_id" name="place_id" class="select select-bordered mt-1">
                            <option value="">{{ __('Any') }}</option>
                            @foreach ($places as $place)
                                <option value="{{ $place->id }}" @selected(request('place_id') == $place->id)>{{ $place->name }}</option>
                            @endforeach
                        </select>
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
                    @if (request()->hasAny(['q', 'from_date', 'to_date', 'place_id', 'tag_id']))
                        <a href="{{ route('browse.activities') }}" class="btn btn-ghost">{{ __('Clear') }}</a>
                    @endif
                </div>
            </form>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($activities as $activity)
                    <x-cards.activity-card :activity="$activity" :wishlist-activity-ids="$wishlistActivityIds ?? []" />
                @empty
                    <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                        {{ __('No activities found.') }}
                    </div>
                @endforelse
            </div>

            @if ($activities->hasPages())
                <div class="rounded-xl border border-base-300 bg-base-100 p-4">{{ $activities->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
