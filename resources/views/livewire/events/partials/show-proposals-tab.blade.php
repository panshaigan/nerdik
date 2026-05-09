<div
    id="ui-event-show-pending-proposals"
    wire:key="event-show-pending-proposals-{{ $organizerProposalRefreshTick }}-{{ $pendingProposals->count() }}"
    class="ui-event-show-pending-proposals p-4 sm:p-6"
    data-ui="event-show-pending-proposals"
>
    <h3 class="mb-1 text-lg font-semibold text-base-content">{{ __('ui.events.pending_proposals') }}</h3>
    <p class="mb-3 text-sm text-base-content/80">{{ __('ui.events.pending_proposals_help') }}</p>
    <ul class="divide-y divide-primary/15">
        @foreach ($pendingProposals as $proposal)
            @php
                $pa = $proposal->activity;
                $durationLabel = format_activity_duration_compact($pa->duration_in_minutes);
                $proposalBadgeItems = $proposalBadgeItemsByProposalId[(int) $proposal->id] ?? [];
            @endphp
            <li class="mb-3 space-y-3 rounded-xl border border-primary/25 bg-base-200/40 p-6">
                @php
                    $freeSlots = $freeSlotsAllForProposals->filter(fn ($s) => $s->fitsProposalActivity($pa))->values();
                @endphp
                <div class="grid grid-cols-2 gap-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <span class="relative inline-block min-w-0 max-w-full">
                                <button
                                    type="button"
                                    wire:click="openActivityPreview({{ (int) $pa->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="openActivityPreview({{ (int) $pa->id }})"
                                    wire:loading.class.delay="cursor-wait"
                                    class="link link-primary min-w-0 break-words text-left text-lg font-medium"
                                    data-ui="event-show-proposal-open-activity-preview"
                                >
                                    {{ $pa->name }}
                                </button>
                                <div
                                    wire:loading.delay
                                    wire:target="openActivityPreview({{ (int) $pa->id }})"
                                    class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded bg-base-100/55 backdrop-blur-[1px]"
                                    aria-live="polite"
                                    role="status"
                                    data-ui="event-show-proposal-activity-preview-loading"
                                >
                                    <span class="sr-only">{{ __('ui.common.loading') }}</span>
                                    <span class="loading loading-spinner loading-lg text-primary" aria-hidden="true"></span>
                                </div>
                            </span>
                            <span class="text-sm text-base-content/70"> · {{ __('ui.common.by') }} {{ $proposal->creator?->displayName() }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        @if ($freeSlotsAllForProposals->isEmpty())
                            <span class="text-sm text-base-content/55">{{ __('ui.events.no_free_slots') }}</span>
                        @elseif ($freeSlots->isEmpty())
                            <span class="text-sm text-base-content/55">{{ __('ui.events.no_compatible_slots') }}</span>
                        @else
                            @php
                                $acceptSlotSelectOptions = $freeSlots->map(fn ($s) => [
                                    'id' => $s->id,
                                    'name' => $s->proposalAcceptOptionLabel(),
                                ])->values()->all();
                            @endphp
                            <div class="inline-flex max-w-full flex-wrap items-center gap-1" wire:key="proposal-accept-{{ $proposal->id }}">
                                <x-select
                                    wire:model="proposalAcceptSlotId.{{ $proposal->id }}"
                                    :options="$acceptSlotSelectOptions"
                                    :placeholder="__('ui.events.choose_slot_or_auto')"
                                    placeholder-value=""
                                    class="select-sm max-w-md min-w-[12rem] flex-1"
                                    :error-field="'proposalAcceptSlot.'.$proposal->id"
                                />
                                <x-button
                                    type="button"
                                    class="btn-success btn-square btn-sm shrink-0"
                                    :title="__('ui.events.accept')"
                                    wire:click="acceptPendingProposal({{ $proposal->id }})"
                                    wire:loading.attr="disabled"
                                >
                                    <span class="sr-only">{{ __('ui.events.accept') }}</span>
                                    <x-icon name="o-check" class="h-5 w-5" />
                                </x-button>
                            </div>
                        @endif
                        <x-button
                            type="button"
                            class="btn-error btn-square btn-sm shrink-0"
                            :title="__('ui.events.reject')"
                            wire:click="rejectPendingProposal({{ $proposal->id }})"
                            wire:loading.attr="disabled"
                        >
                            <span class="sr-only">{{ __('ui.events.reject') }}</span>
                            <x-icon name="o-x-mark" class="h-5 w-5" />
                        </x-button>
                    </div>
                </div>
                <x-ui.activity-badge-group
                    :items="$proposalBadgeItems"
                    class="mt-0.5"
                    data-ui="event-show-proposal-badge-group"
                />
                <div class="space-y-1.5 min-w-0">
                    @if ($durationLabel !== null || filled($pa->max_participants))
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm">
                            @if ($durationLabel !== null)
                                <span class="inline-flex shrink-0 items-center gap-1.5 text-base-content/70" title="{{ __('ui.activities.show_duration') }}">
                                    <x-icon name="o-clock" class="h-4 w-4 shrink-0 text-base-content/50" />
                                    <span class="tabular-nums">{{ $durationLabel }}</span>
                                </span>
                            @endif
                            @if (filled($pa->max_participants))
                                <span class="inline-flex shrink-0 items-center gap-1.5 tabular-nums text-base-content/60" title="{{ __('ui.activities.max_participants') }}">
                                    <x-icon name="o-users" class="h-4 w-4 shrink-0" />
                                    <span>{{ $pa->max_participants }}</span>
                                </span>
                            @endif
                        </div>
                    @endif
                    @if ($proposal->proposedSlots->isNotEmpty())
                        <p class="text-sm text-base-content/80">
                            <span class="font-medium text-base-content">{{ __('ui.events.preferred_slots') }}:</span>
                            {{ $proposal->proposedSlots->pluck('name')->join(', ') }}
                        </p>
                    @endif
                    @if ($proposal->preferred_start_time)
                        <p class="text-sm text-base-content/80">
                            <span class="font-medium text-base-content">{{ __('ui.events.preferred_time') }}:</span>
                            {{ format_in_user_tz($proposal->preferred_start_time) }}
                        </p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
</div>
