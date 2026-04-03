@props([
    'activity',
    'wishlistActivityIds' => [],
])

<article class="ui-card ui-card-activity card border border-base-300 bg-base-100 shadow-sm" data-ui="activity-card" id="ui-activity-card-{{ $activity->id }}">
    <div class="card-body p-5" data-ui="activity-card-body">
        <div class="flex items-start justify-between gap-2">
            <h3 class="card-title text-xl leading-tight">
                <a href="{{ route('activities.show', $activity) }}" class="link link-primary ui-link ui-link-title" data-ui="activity-card-title-link">{{ $activity->name }}</a>
            </h3>
            @auth
                <div class="shrink-0">
                    @if (in_array($activity->id, $wishlistActivityIds))
                        <form action="{{ route('wishlist.activities.remove', $activity) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" class="btn-ghost btn-sm text-warning ui-action ui-action-wishlist-remove" :title="__('Remove from wishlist')" data-ui="activity-card-wishlist-remove">★</x-button>
                        </form>
                    @else
                        <form action="{{ route('wishlist.activities.add', $activity) }}" method="POST" class="inline">
                            @csrf
                            <x-button type="submit" class="btn-ghost btn-sm ui-action ui-action-wishlist-add" :title="__('Add to wishlist')" data-ui="activity-card-wishlist-add">☆</x-button>
                        </form>
                    @endif
                </div>
            @endauth
        </div>

        <p class="text-sm opacity-70">{{ ucfirst($activity->type) }}</p>
        @if ($activity->host)
            <p class="text-sm opacity-70">{{ __('Host') }}: {{ $activity->host->nickname ?? $activity->host->email }}</p>
        @endif
        @if ($activity->slot && $activity->slot->event)
            <p class="text-sm opacity-70">{{ $activity->slot->event->name }}</p>
        @endif

        <div class="mt-2">
            @include('tags.partials.inline', ['tags' => $activity->tags])
        </div>
    </div>
</article>
