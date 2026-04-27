<div id="ui-event-show-pending-proposals" class="ui-event-show-pending-proposals p-6" data-ui="event-show-pending-proposals">
    <h3 class="mb-1 text-lg font-medium text-base-content">{{ __('ui.events.pending_proposals') }}</h3>
    <p class="mb-3 text-sm text-base-content/80">{{ __('ui.events.pending_proposals_help') }}</p>
    <ul class="divide-y divide-base-300">
        @foreach ($pendingProposals as $proposal)
            @php
                $pa = $proposal->activity;
                $durationLabel = format_activity_duration_compact($pa->duration_in_minutes);
                $proposalBadgeItems = app(\App\Domain\ActivityBadges\ActivityBadgeGroupBuilder::class)->build(
                    $pa,
                    \App\Domain\ActivityBadges\ActivityBadgeGroupConfig::eventProposal(),
                );
            @endphp
            <li class="p-6 mb-3 space-y-3 bg-base-200 rounded-lg">
                @php
                    $freeSlotsAll = $event->slots->whereNull('activity_id')->values();
                    $freeSlots = $freeSlotsAll->filter(fn ($s) => $s->fitsProposalActivity($pa))->values();
                @endphp
                <div class="grid grid-cols-2 gap-2">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                            <a href="{{ route('activities.show', $pa) }}" class="link link-primary min-w-0 font-medium break-words text-lg">
                                {{ $pa->name }}
                            </a>
                            <span class="text-sm text-base-content/70"> · {{ __('ui.common.by') }} {{ $proposal->creator->nickname ?? $proposal->creator->email }}</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2">
                        @if ($freeSlotsAll->isEmpty())
                            <span class="text-sm text-base-content/50">{{ __('ui.events.no_free_slots') }}</span>
                        @elseif ($freeSlots->isEmpty())
                            <span class="text-sm text-base-content/50">{{ __('ui.events.no_compatible_slots') }}</span>
                        @else
                            @php
                                $acceptSlotSelectOptions = $freeSlots->map(fn ($s) => [
                                    'id' => $s->id,
                                    'name' => $s->proposalAcceptOptionLabel(),
                                ])->values()->all();
                            @endphp
                            <div class="inline-flex max-w-full flex-wrap items-center gap-1" wire:key="proposal-accept-{{ $proposal->id }}">
                                <x-select
                                    wire:model.live="proposalAcceptSlotId.{{ $proposal->id }}"
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
                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
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
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
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
                                    <svg class="h-4 w-4 shrink-0 text-base-content/50" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                    </svg>
                                    <span class="tabular-nums">{{ $durationLabel }}</span>
                                </span>
                            @endif
                            @if (filled($pa->max_participants))
                                <span class="inline-flex shrink-0 items-center gap-1.5 tabular-nums text-base-content/60" title="{{ __('ui.activities.max_participants') }}">
                                    <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                    </svg>
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
