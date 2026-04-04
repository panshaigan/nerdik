@php
    $logoUrl = $activity->logo_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($activity->logo_path)
        : null;
    $slot = $activity->slot;
    $event = $slot?->event;
    $langList = collect($activity->languages ?? [])
        ->filter(fn ($l) => is_string($l) && trim($l) !== '')
        ->values();
    $hostRoleLabel = \Illuminate\Support\Facades\Lang::has('ui.activities.host_title.'.$activity->type)
        ? __('ui.activities.host_title.'.$activity->type)
        : __('Host');
    $hasDetailGrid = $activity->min_participants !== null
        || (bool) $activity->duration_minutes
        || $activity->signoff_deadline_hours !== null
        || $activity->price !== null
        || $langList->isNotEmpty();
@endphp

<div class="py-10 sm:py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="alert" class="alert alert-success text-sm">{{ session('status') }}</div>
        @endif

        {{-- Hero --}}
        <div
            class="ui-activity-show-hero overflow-hidden rounded-xl border border-base-300 bg-base-100 shadow"
            data-ui="activity-show-hero"
        >
            <div class="relative min-h-[140px] bg-gradient-to-br from-primary/20 via-base-200/50 to-base-100 sm:min-h-[180px]">
                @if ($logoUrl)
                    <div class="absolute inset-0 opacity-30">
                        <img src="{{ $logoUrl }}" alt="" class="h-full w-full object-cover" />
                    </div>
                @endif
                <div class="relative flex flex-col gap-4 p-6 sm:flex-row sm:items-start sm:justify-between sm:p-8">
                    <div class="min-w-0 flex-1 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                            {{ ucfirst($activity->type) }}
                        </p>
                        <h1 class="text-2xl font-semibold leading-tight text-base-content sm:text-3xl">
                            {{ $activity->name }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-base-content/75">
                            @if (! $activity->passive_host && $activity->host)
                                <span>{{ $hostRoleLabel }}: {{ $activity->host->nickname ?? $activity->host->email }}</span>
                            @endif
                            @if ($activity->creator && (int) ($activity->host_user_id ?? 0) !== (int) $activity->creator->id)
                                <span class="text-base-content/60">
                                    {{ __('Created by') }} {{ $activity->creator->nickname ?? $activity->creator->email }}
                                </span>
                            @endif
                        </div>
                    </div>
                    @auth
                        <div class="shrink-0 self-end sm:pt-0.5">
                            @if ($inWishlist)
                                <form action="{{ route('wishlist.activities.remove', $activity) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" class="btn-ghost btn-sm text-lg text-warning ui-action ui-action-wishlist-remove" :title="__('Remove from wishlist')" data-ui="activity-show-wishlist-remove">★</x-button>
                                </form>
                            @else
                                <form action="{{ route('wishlist.activities.add', $activity) }}" method="POST" class="inline">
                                    @csrf
                                    <x-button type="submit" class="btn-ghost btn-sm text-lg ui-action ui-action-wishlist-add" :title="__('Add to wishlist')" data-ui="activity-show-wishlist-add">☆</x-button>
                                </form>
                            @endif
                        </div>
                    @endauth
                </div>
            </div>

            @if ($activity->tags->isNotEmpty() || filled($activity->age_limit) || $activity->is_restricted || $activity->open_for_observers)
                <div class="flex flex-wrap items-center gap-1 border-t border-base-300 bg-base-100/80 px-4 py-2 sm:px-6">
                    @if ($activity->tags->isNotEmpty())
                        @include('tags.partials.inline', ['tags' => $activity->tags, 'class' => ''])
                    @endif
                    @if ($activity->is_restricted)
                        <span class="badge badge-primary badge-outline whitespace-normal text-left">{{ __('ui.activities.restricted') }}</span>
                    @endif
                    @if ($activity->open_for_observers)
                        <span class="badge badge-primary badge-outline whitespace-normal text-left">{{ __('ui.activities.open_for_observers') }}</span>
                    @endif
                    @if (filled($activity->age_limit))
                        <span class="badge badge-primary badge-outline tabular-nums">{{ $activity->age_limit }}+</span>
                    @endif
                </div>
            @endif

            <div class="space-y-6 p-6 sm:p-8">
                @if ($hasDetailGrid)
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-5 text-sm sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                        @if ($activity->min_participants !== null)
                            <div class="min-w-0">
                                <dt class="text-xs text-base-content/60">{{ __('ui.activities.min_participants') }}</dt>
                                <dd class="mt-0.5 font-medium tabular-nums">{{ $activity->min_participants }}</dd>
                            </div>
                        @endif
                        @if ($activity->duration_minutes)
                            <div class="min-w-0">
                                <dt class="text-xs text-base-content/60">{{ __('ui.activities.show_duration') }}</dt>
                                <dd class="mt-0.5 font-medium tabular-nums">{{ $activity->duration_minutes }} min</dd>
                            </div>
                        @endif
                        @if ($activity->signoff_deadline_hours !== null)
                            <div class="min-w-0">
                                <dt class="text-xs text-base-content/60">{{ __('ui.activities.show_signoff') }}</dt>
                                <dd class="mt-0.5 font-medium tabular-nums">{{ $activity->signoff_deadline_hours }} h</dd>
                            </div>
                        @endif
                        @if ($activity->price !== null)
                            <div class="min-w-0">
                                <dt class="text-xs text-base-content/60">{{ __('ui.activities.show_price') }}</dt>
                                <dd class="mt-0.5 font-medium tabular-nums">{{ number_format((float) $activity->price, 2) }}</dd>
                            </div>
                        @endif
                        @if ($langList->isNotEmpty())
                            <div class="min-w-0 sm:col-span-2 lg:col-span-2 xl:col-span-2">
                                <dt class="text-xs text-base-content/60">{{ __('ui.activities.show_languages') }}</dt>
                                <dd class="mt-0.5 font-medium">{{ $langList->join(', ') }}</dd>
                            </div>
                        @endif
                    </dl>
                @endif

                <div @class(['border-t border-base-300 pt-6' => $hasDetailGrid])>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-base-content/50">{{ __('ui.activities.show_about') }}</p>
                    @if (filled(rich_text_excerpt($activity->desc)))
                        <div class="rich-text-content max-w-3xl text-sm leading-relaxed text-base-content/90">
                            {!! rich_text($activity->desc) !!}
                        </div>
                    @else
                        <p class="text-sm text-base-content/60">{{ __('ui.activities.show_no_description') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Schedule / event --}}
            <div class="rounded-xl border border-base-300 bg-base-100 p-6 shadow-sm sm:p-8" data-ui="activity-show-schedule">
                <h2 class="mb-4 text-lg font-semibold text-base-content">{{ __('ui.activities.show_schedule') }}</h2>
                @if ($event)
                    <div class="flex flex-col gap-4 rounded-lg border border-base-300 bg-base-200/30 p-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">{{ __('ui.activities.show_at_event') }}</p>
                            <a href="{{ route('events.show', $event) }}" wire:navigate class="link link-primary text-lg font-medium">
                                {{ $event->name }}
                            </a>
                            @if ($slot->starts_at || $slot->ends_at)
                                <p class="text-sm text-base-content/70 tabular-nums">
                                    @if ($slot->starts_at && $slot->ends_at)
                                        {{ format_in_user_tz($slot->starts_at, 'D, M j · H:i') }}
                                        <span class="text-base-content/50">–</span>
                                        {{ format_in_user_tz($slot->ends_at, 'H:i') }}
                                    @elseif ($slot->starts_at)
                                        {{ format_in_user_tz($slot->starts_at, 'D, M j · H:i') }}
                                    @elseif ($slot->ends_at)
                                        {{ format_in_user_tz($slot->ends_at, 'D, M j · H:i') }}
                                    @endif
                                </p>
                            @endif
                            @if ($slot->place)
                                <p class="text-sm text-base-content/70">{{ $slot->place->venueRoomLabel() }}</p>
                            @endif
                        </div>
                    </div>
                @else
                    <p class="text-sm text-base-content/70">{{ __('ui.activities.show_open_run') }}</p>
                @endif
            </div>

            <div class="rounded-xl border border-base-300 bg-base-100 p-6 shadow-sm sm:p-8" data-ui="activity-show-participants">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold text-base-content">{{ __('ui.activities.show_participants') }}</h2>
                    <p class="shrink-0 text-lg font-medium tabular-nums text-base-content/90">
                        {{ $activity->participants->count() }}
                        @if ($activity->max_participants !== null)
                            <span class="text-base-content/50">/</span>{{ $activity->max_participants }}
                        @else
                            <span class="text-base-content/50">/</span>∞
                        @endif
                    </p>
                </div>
                @auth
                    @if ($isParticipant || $onWaitlist || $canJoin)
                        <div class="mb-4 flex flex-wrap gap-2" data-ui="activity-show-participation-actions">
                            @if ($isParticipant)
                                <form action="{{ route('activities.leave', $activity) }}" method="POST" class="inline">
                                    @csrf
                                    <x-button type="submit" class="btn-error">{{ __('Leave activity') }}</x-button>
                                </form>
                            @elseif ($onWaitlist)
                                <form action="{{ route('activities.leave-waitlist', $activity) }}" method="POST" class="inline">
                                    @csrf
                                    <x-button type="submit" class="btn-neutral">{{ __('Leave waitlist') }}</x-button>
                                </form>
                            @elseif ($canJoin)
                                @if (! $isFull)
                                    <form action="{{ route('activities.join', $activity) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button type="submit" class="btn-primary">{{ __('Join activity') }}</x-button>
                                    </form>
                                    <form action="{{ route('activities.join-waitlist', $activity) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button type="submit" class="btn-outline">{{ __('Join waitlist') }}</x-button>
                                    </form>
                                @else
                                    <form action="{{ route('activities.join-waitlist', $activity) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button type="submit" class="btn-warning">{{ __('Join waitlist') }}</x-button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    @endif
                @endauth
                <ul class="divide-y divide-base-300">
                    @forelse ($activity->participants as $p)
                        <li class="flex items-center justify-between gap-3 py-3 first:pt-0">
                            <span class="min-w-0 text-sm">
                                {{ $p->user->nickname ?? $p->user->email }}
                                @if ($p->is_host)
                                    <span class="ml-1 text-base-content/60">({{ __('Host') }})</span>
                                @endif
                                @if ($p->is_absent)
                                    <span class="ml-1 text-error">({{ __('Absent') }})</span>
                                @endif
                            </span>
                            @if ($isHost && ! $p->is_host && ! $p->is_absent)
                                <form action="{{ route('activity-participants.mark-absent', $p) }}" method="POST" class="inline shrink-0">
                                    @csrf
                                    <x-button type="submit" class="btn-ghost btn-xs text-error">{{ __('Mark absent') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-sm text-base-content/60">{{ __('No participants yet.') }}</li>
                    @endforelse
                </ul>
            </div>

            @if ($activity->waitlist->isNotEmpty())
                <div class="rounded-xl border border-base-300 bg-base-100 p-6 shadow-sm sm:p-8 lg:col-span-2" data-ui="activity-show-waitlist">
                    <h2 class="mb-4 text-lg font-semibold text-base-content">{{ __('ui.activities.show_waitlist') }}</h2>
                    <ul class="divide-y divide-base-300">
                        @foreach ($activity->waitlist as $entry)
                            <li class="py-3 text-sm first:pt-0">
                                <span class="tabular-nums text-base-content/60">#{{ $entry->position }}</span>
                                {{ $entry->user->nickname ?? $entry->user->email }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
