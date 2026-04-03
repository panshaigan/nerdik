@props([
    'event',
    'wishlistEventIds' => [],
])

<article class="ui-card ui-card-event card border border-base-300 bg-base-100 shadow-sm" data-ui="event-card" id="ui-event-card-{{ $event->id }}">
    <div class="card-body p-5" data-ui="event-card-body">
        <div class="flex items-start justify-between gap-2">
            <h3 class="card-title text-xl leading-tight">
                @auth
                    @if ($event->created_by === auth()->id())
                        <a href="{{ route('events.edit', $event) }}" class="link link-primary ui-link ui-link-title" data-ui="event-card-title-link">{{ $event->name }}</a>
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
                            <x-button type="submit" class="btn-ghost btn-sm text-warning ui-action ui-action-wishlist-remove" :title="__('Remove from wishlist')" data-ui="event-card-wishlist-remove">★</x-button>
                        </form>
                    @else
                        <form action="{{ route('wishlist.events.add', $event) }}" method="POST" class="inline">
                            @csrf
                            <x-button type="submit" class="btn-ghost btn-sm ui-action ui-action-wishlist-add" :title="__('Add to wishlist')" data-ui="event-card-wishlist-add">☆</x-button>
                        </form>
                    @endif
                </div>
            @endauth
        </div>

        @if ($event->hostDisplayName())
            <p class="text-sm opacity-70">{{ __('Host') }}: {{ $event->hostDisplayName() }}</p>
        @endif
        <p class="text-sm opacity-70">{{ format_in_user_tz($event->starts_at) }} – {{ format_in_user_tz($event->ends_at) }}</p>
        @if (filled(rich_text_excerpt($event->desc)))
            <p class="text-sm opacity-80">{{ rich_text_excerpt($event->desc, 120) }}</p>
        @endif

        <div class="mt-2">
            @include('tags.partials.inline', ['tags' => $event->tags])
        </div>

        <div class="card-actions mt-2 items-center justify-between">
            <x-button :link="route('events.show', $event)" class="btn-primary btn-sm ui-action ui-action-open-event" data-ui="event-card-open">
                {{ __('View event & propose activity') }}
            </x-button>
            <x-button
                type="button"
                x-data="{ copied: false }"
                x-on:click="navigator.clipboard.writeText('{{ route('events.show', $event) }}'); copied = true; setTimeout(() => copied = false, 2000)"
                class="btn-ghost btn-sm ui-action ui-action-share"
                :title="__('Copy link')"
                data-ui="event-card-share"
            >
                <span x-show="!copied">{{ __('Share') }}</span>
                <span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
            </x-button>
        </div>
    </div>
</article>
