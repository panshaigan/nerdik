@props([
    'event',
    'wishlistEventIds' => [],
])

<article class="card border border-base-300 bg-base-100 shadow-sm">
    <div class="card-body p-5">
        <div class="flex items-start justify-between gap-2">
            <h3 class="card-title text-xl leading-tight">
                @auth
                    @if ($event->created_by === auth()->id())
                        <a href="{{ route('events.edit', $event) }}" class="hover:underline">{{ $event->name }}</a>
                    @else
                        <span>{{ $event->name }}</span>
                    @endif
                @else
                    <span>{{ $event->name }}</span>
                @endauth
            </h3>
            @auth
                <div class="shrink-0">
                    @if (in_array($event->id, $wishlistEventIds))
                        <form action="{{ route('wishlist.events.remove', $event) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-ghost btn-sm text-warning" title="{{ __('Remove from wishlist') }}">★</button>
                        </form>
                    @else
                        <form action="{{ route('wishlist.events.add', $event) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm" title="{{ __('Add to wishlist') }}">☆</button>
                        </form>
                    @endif
                </div>
            @endauth
        </div>

        @if ($event->hostDisplayName())
            <p class="text-sm opacity-70">{{ __('Host') }}: {{ $event->hostDisplayName() }}</p>
        @endif
        <p class="text-sm opacity-70">{{ format_in_user_tz($event->starts_at) }} – {{ format_in_user_tz($event->ends_at) }}</p>
        @if ($event->desc)
            <p class="text-sm opacity-80">{{ \Illuminate\Support\Str::limit($event->desc, 120) }}</p>
        @endif

        <div class="mt-2">
            @include('tags.partials.inline', ['tags' => $event->tags])
        </div>

        <div class="card-actions mt-2 items-center justify-between">
            <a href="{{ route('events.show', $event) }}" class="btn btn-primary btn-sm">
                {{ __('View event & propose activity') }}
            </a>
            <button
                type="button"
                x-data="{ copied: false }"
                x-on:click="navigator.clipboard.writeText('{{ route('events.show', $event) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                class="btn btn-ghost btn-sm"
                title="{{ __('Copy link') }}"
            >
                <span x-show="!copied">{{ __('Share') }}</span>
                <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
            </button>
        </div>
    </div>
</article>
