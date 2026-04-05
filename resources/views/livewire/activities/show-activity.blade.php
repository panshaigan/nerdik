@php
    $logoUrl = $activity->logo_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($activity->logo_path)
        : null;
    $slot = $activity->slot;
    $event = $slot?->event;
    $hostRoleLabel = \Illuminate\Support\Facades\Lang::has('ui.activities.host_title.'.$activity->type->value)
        ? __('ui.activities.host_title.'.$activity->type->value)
        : __('Host');
    $hasOpenRunBlurb = $slot && ! $event;
    $showEventCard = $event || $hasOpenRunBlurb;
    $hasMetaStats = $activity->min_participants !== null
        || (bool) $activity->duration_in_minutes
        || $activity->cancellation_deadline_in_hours !== null
        || $activity->price !== null;
    $hasDetailRow = $showEventCard || $hasMetaStats;
    $metaItems = [];
    if ($activity->min_participants !== null) {
        $metaItems[] = [
            'label' => __('ui.activities.min_participants'),
            'value' => (string) $activity->min_participants,
        ];
    }
    if ($activity->duration_in_minutes) {
        $metaItems[] = [
            'label' => __('ui.activities.show_duration'),
            'value' => $activity->duration_in_minutes.' min',
        ];
    }
    if ($activity->cancellation_deadline_in_hours !== null) {
        $metaItems[] = [
            'label' => __('ui.activities.show_cancellation_deadline'),
            'value' => $activity->cancellation_deadline_in_hours.' h',
        ];
    }
    if ($activity->price !== null) {
        $metaItems[] = [
            'label' => __('ui.activities.show_price'),
            'value' => number_format((float) $activity->price, 2),
        ];
    }
    $metaRows = count($metaItems) > 0
        ? array_chunk($metaItems, (int) ceil(count($metaItems) / 2))
        : [];
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
                <div class="relative z-10 flex flex-col gap-4 p-6 sm:p-8">
                    <div class="flex items-start gap-3 sm:gap-4" dir="ltr">
                        <div class="min-w-0 flex-1 space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ ucfirst($activity->type->value) }}
                            </p>
                            <h1 class="text-2xl font-semibold leading-tight text-base-content sm:text-3xl">
                                {{ $activity->name }}
                            </h1>
                            @if ($activity->tags->isNotEmpty() || filled($activity->minimum_age) || $activity->requires_approval || $activity->allows_observers)
                                <div class="flex flex-wrap items-center gap-1 pt-0.5">
                                    @if ($activity->tags->isNotEmpty())
                                        @include('tags.partials.inline', ['tags' => $activity->tags, 'class' => ''])
                                    @endif
                                    @if ($activity->requires_approval)
                                        <span class="badge badge-primary badge-outline whitespace-normal text-left">{{ __('ui.activities.requires_approval_badge') }}</span>
                                    @endif
                                    @if ($activity->allows_observers)
                                        <span class="badge badge-primary badge-outline whitespace-normal text-left">{{ __('ui.activities.allows_observers_badge') }}</span>
                                    @endif
                                    @if (filled($activity->minimum_age))
                                        <span class="badge badge-primary badge-outline tabular-nums">{{ $activity->minimum_age }}+</span>
                                    @endif
                                </div>
                            @endif
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-base-content/75">
                                @if (! $activity->is_host_passive && $activity->creator)
                                    <span>{{ $hostRoleLabel }}: {{ $activity->creator->nickname ?? $activity->creator->email }}</span>
                                @endif
                            </div>
                        </div>
                        @auth
                            <div class="flex shrink-0 items-center gap-1 pt-0.5 sm:pt-1" data-ui="activity-show-hero-actions">
                                @if ($canManageActivity)
                                    <x-button
                                        :link="route('activities.edit', $activity)"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                        :title="__('Edit')"
                                        :aria-label="__('Edit').': '.$activity->name"
                                        data-ui="activity-show-edit"
                                    >
                                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                        </svg>
                                    </x-button>
                                    <form
                                        action="{{ route('activities.destroy', $activity) }}"
                                        method="POST"
                                        class="inline"
                                        onsubmit="return confirm({{ json_encode(__('Are you sure you want to delete this activity?')) }})"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <x-button
                                            type="submit"
                                            class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                            :title="__('Delete')"
                                            :aria-label="__('Delete').': '.$activity->name"
                                            data-ui="activity-show-delete"
                                        >
                                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </x-button>
                                    </form>
                                @endif
                                @if ($inWishlist)
                                    <form action="{{ route('wishlist.activities.remove', $activity) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" class="btn btn-ghost btn-square btn-sm text-lg text-warning ui-action ui-action-wishlist-remove" :title="__('Remove from wishlist')" data-ui="activity-show-wishlist-remove">★</x-button>
                                    </form>
                                @else
                                    <form action="{{ route('wishlist.activities.add', $activity) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button type="submit" class="btn btn-ghost btn-square btn-sm text-lg ui-action ui-action-wishlist-add" :title="__('Add to wishlist')" data-ui="activity-show-wishlist-add">☆</x-button>
                                    </form>
                                @endif
                            </div>
                        @endauth
                    </div>
                </div>
            </div>

            <div class="space-y-6 border-t border-base-300 p-6 pt-7 sm:p-8 sm:pt-8">
                @if ($hasDetailRow)
                    <div
                        @class([
                            'activity-show-detail-layout',
                            'activity-show-detail-layout--split' => $showEventCard && $hasMetaStats,
                        ])
                        data-ui="activity-show-detail-row"
                    >
                        @if ($showEventCard)
                            <div @class(['activity-show-detail-event-cell', 'w-full' => ! $hasMetaStats])>
                                <div
                                    class="max-w-full rounded-lg border border-base-300 bg-base-200/30 p-4 sm:max-w-sm"
                                    data-ui="activity-show-event-card"
                                >
                                    @if ($event)
                                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">{{ __('ui.activities.show_at_event') }}</p>
                                        <a href="{{ route('events.show', $event) }}" wire:navigate class="link link-primary mt-1 inline-block text-lg font-medium break-words">
                                            {{ $event->name }}
                                        </a>
                                        @if ($slot && ($slot->starts_at || $slot->ends_at || $slot->place))
                                            <div class="mt-5 space-y-1.5 border-t border-base-300/80 pt-5">
                                                @if ($slot->starts_at || $slot->ends_at)
                                                    <p class="text-sm tabular-nums text-base-content/80">
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
                                        @endif
                                    @else
                                        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">{{ __('ui.activities.show_schedule') }}</p>
                                        <p class="mt-1 text-sm font-medium leading-snug text-base-content/90">
                                            {{ __('ui.activities.show_open_run') }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if ($hasMetaStats)
                            <div class="activity-show-detail-meta-cell w-full">
                                <div class="flex flex-col gap-4" data-ui="activity-show-meta-stats">
                                    @foreach ($metaRows as $row)
                                        <div class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                                            @foreach ($row as $item)
                                                <div class="min-w-0 text-sm">
                                                    <p class="block text-xs leading-tight text-base-content/60">{{ $item['label'] }}</p>
                                                    <p class="mt-1 block font-medium tabular-nums text-base-content">{{ $item['value'] }}</p>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <div @class(['border-t border-base-300 pt-6' => $hasDetailRow])>
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

        <div
            class="rounded-xl border border-base-300 bg-base-100 p-6 shadow-sm sm:p-8"
            data-ui="activity-show-participation"
        >
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-base-300 pb-4">
                <h2 class="text-lg font-semibold text-base-content">{{ __('ui.activities.show_participation_section') }}</h2>
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <p class="shrink-0 text-lg font-medium tabular-nums text-base-content/90">
                        {{ $activity->participants->count() }}
                        @if ($activity->max_participants !== null)
                            <span class="text-base-content/50">/</span>{{ $activity->max_participants }}
                        @else
                            <span class="text-base-content/50">/</span>∞
                        @endif
                    </p>
                    @auth
                        @if ($isParticipant || $onWaitlist || $canJoin)
                            <div class="flex flex-wrap gap-2" data-ui="activity-show-participation-actions">
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
                                    @if ($activity->requires_approval || $isFull)
                                        <form action="{{ route('activities.join-waitlist', $activity) }}" method="POST" class="inline">
                                            @csrf
                                            <x-button type="submit" class="btn-warning">{{ __('ui.activities.join_waitlist') }}</x-button>
                                        </form>
                                    @else
                                        <form action="{{ route('activities.join', $activity) }}" method="POST" class="inline">
                                            @csrf
                                            <x-button type="submit" class="btn-primary">{{ __('ui.activities.join') }}</x-button>
                                        </form>
                                    @endif
                                @endif
                            </div>
                        @endif
                    @endauth
                </div>
            </div>
            @auth
                @if (filled($signupBlockedMessage ?? null) && ! $isParticipant && ! $onWaitlist && ! $canJoin)
                    <p class="mb-4 text-sm text-error" data-ui="activity-show-signup-blocked">{{ $signupBlockedMessage }}</p>
                @endif
            @endauth
            <div class="grid gap-8 md:grid-cols-2 md:gap-6" data-ui="activity-show-participation-columns">
                <div class="min-w-0 md:pr-6" data-ui="activity-show-participants">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.activities.show_participants') }}</h3>
                    <ul class="divide-y divide-base-300">
                        @forelse ($activity->participants as $p)
                            <li class="flex items-center justify-between gap-3 py-3 first:pt-0">
                                <span class="min-w-0 text-sm">
                                    {{ $p->user->nickname ?? $p->user->email }}
                                    @if ((int) $p->user_id === (int) ($activity->created_by ?? 0))
                                        <span class="ml-1 text-base-content/60">({{ __('Host') }})</span>
                                    @endif
                                    @if ($p->is_absent)
                                        <span class="ml-1 text-error">({{ __('Absent') }})</span>
                                    @endif
                                </span>
                                @if ($canManageActivity && (int) $p->user_id !== (int) ($activity->created_by ?? 0) && ! $p->is_absent)
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
                <div class="min-w-0 border-t border-base-300 pt-6 md:border-t-0 md:border-l md:pt-0 md:pl-6" data-ui="activity-show-waitlist">
                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.activities.show_waitlist') }}</h3>
                    @if ($activity->waitlist->isEmpty())
                        <p class="text-sm text-base-content/60">{{ __('ui.activities.waitlist_empty_hint') }}</p>
                    @else
                        <ul class="divide-y divide-base-300">
                            @foreach ($activity->waitlist as $entry)
                                <li class="flex flex-wrap items-center justify-between gap-2 py-3 first:pt-0">
                                    <span class="min-w-0 text-sm">
                                        <span class="tabular-nums text-base-content/60">#{{ $entry->position }}</span>
                                        {{ $entry->user->nickname ?? $entry->user->email }}
                                    </span>
                                    @if ($canManageActivity && $activity->requires_approval)
                                        <form
                                            action="{{ route('activities.waitlist.approve', [$activity, $entry]) }}"
                                            method="POST"
                                            class="inline shrink-0"
                                            data-ui="activity-show-waitlist-approve"
                                        >
                                            @csrf
                                            <x-button type="submit" class="btn-primary btn-sm">{{ __('ui.activities.approve_from_waitlist') }}</x-button>
                                        </form>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
