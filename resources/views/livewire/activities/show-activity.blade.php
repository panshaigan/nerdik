@php
    $logoUrl = $activity->logo_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($activity->logo_path)
        : null;
    $activityTypeSlug = $activity->activityType?->slug;
    $activityTypeLabel = $activityTypeSlug ? __('ui.activities.types.'.$activityTypeSlug) : __('ui.common.none');
    $slot = $activity->slot;
    $event = $slot?->event;
    $selfHosted = $activity->hosting_mode === \App\Models\Activity::HOSTING_MODE_SELF_HOSTED;
    $isCancelled = $activity->isCancelled();
    $selfHostedPlace = $activity->place;
    $hostRoleLabel = $activityTypeSlug && \Illuminate\Support\Facades\Lang::has('ui.activities.host_title.'.$activityTypeSlug)
        ? __('ui.activities.host_title.'.$activityTypeSlug)
        : __('ui.activities.host');
    $hasOpenRunBlurb = $slot && ! $event;
    $hostUser = $activity->creator;
    $slotPlace = $slot?->place;
    $schedulePlace = $selfHosted ? $selfHostedPlace : $slotPlace;
    $scheduleVenue = $schedulePlace?->parent ?? $schedulePlace;
    $scheduleRoom = $schedulePlace?->parent ? $schedulePlace->name : null;
    $scheduleStartsAt = $selfHosted ? $activity->starts_at : $slot?->starts_at;
    $scheduleEndsAt = $selfHosted ? $activity->ends_at : $slot?->ends_at;
    $scheduleDateSummary = $scheduleStartsAt && $scheduleEndsAt
        ? format_datetime_range_compact($scheduleStartsAt, $scheduleEndsAt)
        : ($scheduleStartsAt ? format_in_user_tz($scheduleStartsAt, 'D, M j · H:i') : null);
    $scheduleMapConfig = [
        'places' => ($scheduleVenue && $scheduleVenue->latitude !== null && $scheduleVenue->longitude !== null)
            ? [[
                'name' => (string) $scheduleVenue->name,
                'lat' => (float) $scheduleVenue->latitude,
                'lng' => (float) $scheduleVenue->longitude,
            ]]
            : [],
    ];
@endphp

<div class="py-10 sm:py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @if (session('status'))
            <div role="alert" class="alert alert-success text-sm">{{ session('status') }}</div>
        @endif

        @if ($isCancelled)
            <div role="alert" class="alert alert-warning text-sm">
                <div class="space-y-1">
                    <p class="font-medium">{{ __('ui.activities.cancelled_badge') }}</p>
                    @if ($activity->cancel_reason)
                        <p>{{ __('ui.activities.cancel_reason_label') }}: {{ $activity->cancel_reason }}</p>
                    @endif
                    <p class="opacity-80">
                        {{ __('ui.activities.cancelled_meta', [
                            'who' => $activity->canceller?->nickname ?? $activity->canceller?->email ?? __('ui.common.unknown_user'),
                            'when' => $activity->cancelled_at ? format_datetime_in_user_tz($activity->cancelled_at) : '—',
                        ]) }}
                    </p>
                </div>
            </div>
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
                <div class="relative z-10 p-6 sm:p-8">
                    <div class="flex items-start justify-between gap-4 sm:gap-6" dir="ltr">
                        <div class="min-w-0 flex-1 space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
                                {{ $activityTypeLabel }}
                                @if ($event)
                                    @
                                    <a
                                        href="{{ route('events.show', $event) }}"
                                        wire:navigate
                                        class="link link-primary break-words text-end"
                                        data-ui="activity-show-hero-event-link"
                                    >{{ $event->name }}</a>
                                @endif
                            </p>
                            <h1 class="text-2xl font-semibold leading-tight text-base-content sm:text-3xl pb-4">
                                {{ $activity->name }}
                            </h1>
                            <x-ui.activity-badge-group
                                :items="$badgeItems"
                                class="pt-0.5"
                                data-ui="activity-show-badge-group"
                            />
                        </div>
                        @if ((! $activity->is_host_passive && $hostUser) || $activity->duration_in_minutes || $event)
                            <div
                                class="flex min-w-0 max-w-[min(100%,14rem)] shrink-0 flex-col items-end gap-3 text-end sm:max-w-[16rem]"
                                data-ui="activity-show-hero-meta"
                            >
                                @if (! $activity->is_host_passive && $hostUser)
                                    <div class="text-sm">
                                        <p class="block text-xs leading-tight text-base-content/60">{{ $hostRoleLabel }}</p>
                                        <x-user-badge
                                            :user="$hostUser"
                                            size="md"
                                            class="mt-1 flex-row-reverse"
                                            name-class="truncate text-end text-base font-semibold text-base-content"
                                            data-ui="activity-show-host"
                                        />
                                    </div>
                                @endif
                                @if ($activity->duration_in_minutes)
                                    <div class="text-sm">
                                        <p class="block text-xs leading-tight text-base-content/60">{{ __('ui.activities.show_duration') }}</p>
                                        <p class="mt-1 block font-medium tabular-nums text-base-content">{{ $activity->duration_for_humans }}</p>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <x-ui.tabs-with-toolbar
                wire:model.live="tab"
                label-div-class="flex gap-5 overflow-x-auto px-3 pt-2"
                label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="w-full"
                toolbar-wrapper-class="flex shrink-0 items-center gap-1 px-2 pb-2 pt-2 sm:px-3"
                data-ui="activity-show-tabs"
            >
                <x-slot:toolbar>
                    @auth
                        <div class="flex shrink-0 items-center gap-1" data-ui="activity-show-tabs-toolbar">
                            @if ($canManageActivity)
                                <x-button
                                    :link="route('activities.edit', $activity)"
                                    class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                    :title="__('ui.activities.edit')"
                                    :aria-label="__('ui.activities.edit').': '.$activity->name"
                                    data-ui="activity-show-edit"
                                >
                                    <x-ui.icons.pencil class="h-5 w-5 shrink-0" />
                                </x-button>
                                <form
                                    action="{{ route('activities.destroy', $activity) }}"
                                    method="POST"
                                    class="inline"
                                    onsubmit="return confirm({{ json_encode(__('ui.activities.delete_confirm')) }})"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <x-button
                                        type="submit"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-error"
                                        :title="__('ui.activities.delete')"
                                        :aria-label="__('ui.activities.delete').': '.$activity->name"
                                        data-ui="activity-show-delete"
                                    >
                                        <x-ui.icons.trash class="h-5 w-5 shrink-0" />
                                    </x-button>
                                </form>
                                @if ($isCancelled)
                                    <x-button
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-success"
                                        :title="__('ui.activities.reopen_action')"
                                        :aria-label="__('ui.activities.reopen_action')"
                                        wire:click="reopen"
                                        wire:confirm="{{ __('ui.activities.reopen_confirm') }}"
                                    >
                                        ↺
                                    </x-button>
                                @else
                                    <x-button
                                        type="button"
                                        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-warning"
                                        :title="__('ui.activities.cancel_action')"
                                        :aria-label="__('ui.activities.cancel_action')"
                                        wire:click="cancel"
                                        wire:confirm="{{ __('ui.activities.cancel_confirm') }}"
                                    >
                                        ×
                                    </x-button>
                                @endif
                            @endif
                            <x-button
                                :link="route('activities.create', ['duplicate' => $activity->slug])"
                                class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
                                :title="__('ui.activities.duplicate_action')"
                                :aria-label="__('ui.activities.duplicate_action').': '.$activity->name"
                                data-ui="activity-show-duplicate"
                            >
                                <x-ui.icons.duplicate class="h-5 w-5 shrink-0" />
                            </x-button>
                            @if ($hasInterest)
                                <form action="{{ route('interests.activities.remove', $activity) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" class="btn btn-ghost btn-square btn-sm text-lg text-warning ui-action ui-action-interest-remove" :title="__('ui.interests.remove_from_interests')" data-ui="activity-show-interest-remove">★</x-button>
                                </form>
                            @else
                                <form action="{{ route('interests.activities.add', $activity) }}" method="POST" class="inline">
                                    @csrf
                                    <x-button type="submit" class="btn btn-ghost btn-square btn-sm text-lg ui-action ui-action-interest-add" :title="__('ui.interests.add_to_interests')" data-ui="activity-show-interest-add">☆</x-button>
                                </form>
                            @endif
                        </div>
                    @endauth
                </x-slot:toolbar>
                <x-tab name="info" :label="__('ui.activities.show_about')" class="!p-0" data-ui="activity-show-tab-info" icon="o-book-open">
                    <div class="flex flex-col" data-ui="activity-show-info">
                        <div class="px-6 pb-6 pt-6 mb-2 sm:px-8 sm:pt-8">
                            @if (filled(rich_text_excerpt($activity->description)))
                                <div class="rich-text-content text-sm leading-relaxed text-base-content/90">
                                    {!! rich_text($activity->description) !!}
                                </div>
                            @else
                                <p class="text-sm text-base-content/60">{{ __('ui.activities.show_no_description') }}</p>
                            @endif
                        </div>

                        <div
                            class="relative isolate w-full overflow-hidden rounded-b-xl"
                            data-event-show-map-root
                            wire:ignore
                        >
                            <script type="application/json" data-event-show-map-config>@json($scheduleMapConfig)</script>
                            <div
                                data-event-show-map
                                class="relative z-0 w-full bg-base-200/30"
                                style="min-height: 280px; height: min(420px, 52vh);"
                                data-ui="activity-show-schedule-map"
                            ></div>
                            <div class="absolute inset-x-0 bottom-0 z-20 bg-black/60 px-4 py-3 text-white backdrop-blur-[1px]" data-ui="activity-show-schedule-overlay">
                                <div class="space-y-1">
                                    @if ($scheduleVenue)
                                        <p class="text-sm">
                                            <span class="font-semibold text-xl">{{ $scheduleVenue->name }}</span>
                                            @if ($scheduleRoom)
                                                <span>({{ $scheduleRoom }})</span>
                                            @endif
                                            ·
                                            <span>{{ $scheduleVenue?->address ?: __('ui.common.none') }}</span>,
                                            @if ($scheduleVenue?->city)
                                                <span>{{ $scheduleVenue->city->name(app()->getLocale()) }}</span>
                                            @endif
                                        </p>
                                    @endif
                                    <p class="text-xs text-white/90">
                                        {{ $scheduleDateSummary ?: __('ui.activities.show_open_run') }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-tab>

                <x-tab name="participation" :label="__('ui.activities.show_participation_section')" class="p-6 pt-4 sm:p-8 sm:pt-5" data-ui="activity-show-tab-participation" icon="o-users">
                    <div data-ui="activity-show-participation">
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-base-300 pb-4">
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
                                    @if (($isParticipant || $onWaitlist || $canJoin) && !filled($stateBlockedMessage ?? null))
                                        <div class="flex flex-wrap gap-2" data-ui="activity-show-participation-actions">
                                            @if ($isParticipant)
                                                <form action="{{ route('activities.leave', $activity) }}" method="POST" class="inline">
                                                    @csrf
                                                    <x-button type="submit" class="btn-error">{{ __('ui.activities.leave') }}</x-button>
                                                </form>
                                            @elseif ($onWaitlist)
                                                <form action="{{ route('activities.leave-waitlist', $activity) }}" method="POST" class="inline">
                                                    @csrf
                                                    <x-button type="submit" class="btn-neutral">{{ __('ui.activities.leave_waitlist') }}</x-button>
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
                            @if (filled($stateBlockedMessage ?? null))
                                <p class="mb-4 text-sm text-error" data-ui="activity-show-state-blocked">{{ $stateBlockedMessage }}</p>
                            @endif
                            @if (($activeWindowRemainingForActivity ?? null) !== null)
                                <p class="mb-2 text-sm text-base-content/80" data-ui="activity-show-window-activity-cap">
                                    {{ __('ui.events.enrollment_window_activity_spots_remaining', [
                                        'remaining' => $activeWindowRemainingForActivity,
                                        'max' => $activeWindowPerActivityMax,
                                    ]) }}
                                </p>
                            @endif
                            @if (($activeWindowUserRemaining ?? null) !== null)
                                <p class="mb-4 text-sm text-base-content/70" data-ui="activity-show-window-user-cap">
                                    {{ __('ui.events.enrollment_window_user_spots_remaining', ['remaining' => $activeWindowUserRemaining]) }}
                                </p>
                            @endif
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
                                            <x-user-badge
                                                :user="$p->user"
                                                size="sm"
                                                :subline="((int) $p->user_id === (int) ($activity->created_by ?? 0) ? __('ui.activities.host') : null)"
                                                name-class="truncate text-sm font-medium text-base-content"
                                                class="min-w-0 flex-1"
                                            />
                                            @if ($p->is_absent)
                                                <span class="text-xs font-medium text-error">({{ __('ui.activities.absent') }})</span>
                                            @endif
                                            @if ($canManageActivity && (int) $p->user_id !== (int) ($activity->created_by ?? 0))
                                                <div class="flex shrink-0 flex-wrap items-center justify-end gap-1">
                                                    @if ($p->is_absent)
                                                        <form action="{{ route('activity-participants.unmark-absent', $p) }}" method="POST" class="inline">
                                                            @csrf
                                                            <x-button type="submit" class="btn-ghost btn-xs text-success">{{ __('ui.activities.unmark_absent') }}</x-button>
                                                        </form>
                                                    @else
                                                        <form action="{{ route('activity-participants.mark-absent', $p) }}" method="POST" class="inline">
                                                            @csrf
                                                            <x-button type="submit" class="btn-ghost btn-xs text-error">{{ __('ui.activities.mark_absent') }}</x-button>
                                                        </form>
                                                    @endif
                                                    <form
                                                        action="{{ route('activity-participants.move-to-waitlist', $p) }}"
                                                        method="POST"
                                                        class="inline"
                                                        onsubmit='return window.confirm({!! json_encode(__('ui.activities.move_to_waitlist_confirm'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!})'
                                                    >
                                                        @csrf
                                                        <x-button type="submit" class="btn-ghost btn-xs">{{ __('ui.activities.move_to_waitlist') }}</x-button>
                                                    </form>
                                                </div>
                                            @endif
                                        </li>
                                    @empty
                                        <li class="py-2 text-sm text-base-content/60">{{ __('ui.activities.no_participants') }}</li>
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
                                                <div class="flex min-w-0 items-center gap-2">
                                                    <span class="tabular-nums text-xs text-base-content/60">#{{ $entry->position }}</span>
                                                    <x-user-badge
                                                        :user="$entry->user"
                                                        size="sm"
                                                        name-class="truncate text-sm font-medium text-base-content"
                                                    />
                                                </div>
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
                </x-tab>
            </x-ui.tabs-with-toolbar>
        </div>
    </div>
</div>
