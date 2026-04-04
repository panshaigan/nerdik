@php
    $logoUrl = $activity->logo_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($activity->logo_path)
        : null;
    $slot = $activity->slot;
    $event = $slot?->event;
    $langList = collect($activity->languages ?? [])
        ->filter(fn ($l) => is_string($l) && trim($l) !== '')
        ->values();
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
                <div class="relative flex flex-col gap-4 p-6 sm:flex-row sm:items-end sm:justify-between sm:p-8">
                    <div class="min-w-0 flex-1 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                            {{ ucfirst($activity->type) }}
                        </p>
                        <h1 class="text-2xl font-semibold leading-tight text-base-content sm:text-3xl">
                            {{ $activity->name }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-base-content/75">
                            @if ($activity->host)
                                <span>{{ __('Host') }}: {{ $activity->host->nickname ?? $activity->host->email }}</span>
                            @endif
                            @if ($activity->creator && (int) ($activity->host_user_id ?? 0) !== (int) $activity->creator->id)
                                <span class="text-base-content/60">
                                    {{ __('Created by') }} {{ $activity->creator->nickname ?? $activity->creator->email }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-wrap items-center justify-end gap-1">
                        <button
                            type="button"
                            x-data="{ copied: false }"
                            x-on:click="navigator.clipboard.writeText('{{ url()->current() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                            :title="copied ? '{{ __('ui.events.copied') }}' : '{{ __('ui.events.copy_link') }}'"
                            aria-label="{{ __('ui.events.share') }}"
                        >
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 5.314 9.566 5.314m0-10.628L9.566 5.314" />
                            </svg>
                        </button>
                        @auth
                            @if ($canManageActivity)
                                <a
                                    href="{{ route('activities.edit', $activity) }}"
                                    wire:navigate
                                    class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                    title="{{ __('Edit activity') }}"
                                    aria-label="{{ __('Edit activity') }}"
                                >
                                    <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                    </svg>
                                </a>
                            @endif
                        @endauth
                    </div>
                </div>
            </div>

            @if ($activity->tags->isNotEmpty())
                <div class="border-t border-base-300 bg-base-100/80 px-4 py-2 sm:px-6">
                    @include('tags.partials.inline', ['tags' => $activity->tags, 'class' => ''])
                </div>
            @endif

            <div class="grid gap-6 p-6 sm:grid-cols-2 sm:p-8 lg:grid-cols-4">
                <div class="space-y-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">{{ __('ui.activities.show_details') }}</p>
                    <dl class="space-y-3 text-sm">
                        <div class="flex flex-wrap gap-2">
                            @if ($activity->passive_host)
                                <span class="badge badge-outline badge-sm">{{ __('ui.activities.passive_host') }}</span>
                            @endif
                            @if ($activity->is_restricted)
                                <span class="badge badge-warning badge-outline badge-sm">{{ __('ui.activities.restricted') }}</span>
                            @endif
                            @if ($activity->open_for_observers)
                                <span class="badge badge-info badge-outline badge-sm">{{ __('ui.activities.open_for_observers') }}</span>
                            @endif
                        </div>
                        <div>
                            <dt class="text-base-content/60">{{ __('ui.activities.min_participants') }}</dt>
                            <dd class="font-medium tabular-nums">{{ $activity->min_participants ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-base-content/60">{{ __('ui.activities.max_participants') }}</dt>
                            <dd class="font-medium tabular-nums">
                                {{ $activity->participants->count() }}
                                @if ($activity->max_participants !== null)
                                    <span class="text-base-content/50">/</span> {{ $activity->max_participants }}
                                @else
                                    <span class="text-base-content/50">/ ∞</span>
                                @endif
                            </dd>
                        </div>
                        @if ($activity->age_limit !== null)
                            <div>
                                <dt class="text-base-content/60">{{ __('ui.activities.age_limit') }}</dt>
                                <dd class="font-medium tabular-nums">{{ $activity->age_limit }}+</dd>
                            </div>
                        @endif
                        @if ($activity->duration_minutes)
                            <div>
                                <dt class="text-base-content/60">{{ __('ui.activities.show_duration') }}</dt>
                                <dd class="font-medium tabular-nums">{{ $activity->duration_minutes }} min</dd>
                            </div>
                        @endif
                        @if ($activity->signoff_deadline_hours !== null)
                            <div>
                                <dt class="text-base-content/60">{{ __('ui.activities.show_signoff') }}</dt>
                                <dd class="font-medium tabular-nums">{{ $activity->signoff_deadline_hours }} h</dd>
                            </div>
                        @endif
                        @if ($activity->price !== null)
                            <div>
                                <dt class="text-base-content/60">{{ __('ui.activities.show_price') }}</dt>
                                <dd class="font-medium tabular-nums">{{ number_format((float) $activity->price, 2) }}</dd>
                            </div>
                        @endif
                        @if ($langList->isNotEmpty())
                            <div>
                                <dt class="text-base-content/60">{{ __('ui.activities.show_languages') }}</dt>
                                <dd class="font-medium">{{ $langList->join(', ') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="space-y-3 sm:col-span-2 lg:col-span-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">{{ __('ui.activities.show_about') }}</p>
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

        {{-- Participation --}}
        @auth
            <div class="rounded-xl border border-base-300 bg-base-100 p-6 shadow-sm sm:p-8" data-ui="activity-show-participation">
                <h2 class="mb-4 text-lg font-semibold text-base-content">{{ __('ui.activities.show_participation') }}</h2>
                <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center">
                    <div class="flex flex-wrap gap-2">
                        @if ($inWishlist)
                            <form action="{{ route('wishlist.activities.remove', $activity) }}" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" class="btn-outline btn-sm text-warning">{{ __('Remove from wishlist') }}</x-button>
                            </form>
                        @else
                            <form action="{{ route('wishlist.activities.add', $activity) }}" method="POST" class="inline">
                                @csrf
                                <x-button type="submit" class="btn-outline btn-sm">{{ __('Add to wishlist') }}</x-button>
                            </form>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2 sm:ml-auto">
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
                                    <x-button type="submit" class="btn-outline btn-sm">{{ __('Join waitlist') }}</x-button>
                                </form>
                            @else
                                <form action="{{ route('activities.join-waitlist', $activity) }}" method="POST" class="inline">
                                    @csrf
                                    <x-button type="submit" class="btn-warning">{{ __('Join waitlist') }}</x-button>
                                </form>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        @endauth

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-base-300 bg-base-100 p-6 shadow-sm sm:p-8" data-ui="activity-show-participants">
                <h2 class="mb-4 text-lg font-semibold text-base-content">{{ __('ui.activities.show_participants') }}</h2>
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
                <div class="rounded-xl border border-base-300 bg-base-100 p-6 shadow-sm sm:p-8" data-ui="activity-show-waitlist">
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

        <div class="flex flex-wrap items-center gap-4 border-t border-base-300 pt-6 text-sm">
            <a href="{{ route('activities.index') }}" wire:navigate class="link link-hover text-base-content/80">
                {{ __('ui.activities.show_back') }}
            </a>
        </div>
    </div>
</div>
