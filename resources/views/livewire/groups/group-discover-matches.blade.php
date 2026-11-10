<div class="space-y-4" data-ui="group-discover-matches">
    <h2 class="text-lg font-medium text-base-content">{{ __('ui.groups.discover_title') }}</h2>

    @if (! $hasCriteria)
        <p class="rounded-lg border border-dashed border-base-300 bg-base-100 px-4 py-6 text-sm text-base-content/70">
            {{ __('ui.groups.discover_empty_no_prefs') }}
        </p>
    @elseif ($candidates->isEmpty())
        <p class="rounded-lg border border-dashed border-base-300 bg-base-100 px-4 py-6 text-sm text-base-content/70">
            {{ __('ui.groups.discover_empty_no_matches') }}
        </p>
    @else
        <ul class="space-y-2" role="list">
            @foreach ($candidates as $candidate)
                @php
                    $matchTagLabels = $candidate->discovery_match_tag_labels ?? [];
                    $playAreaCaption = $candidate->discovery_match_play_area_caption;
                @endphp
                <li
                    wire:key="group-discover-candidate-{{ $candidate->id }}"
                    class="flex items-start justify-between gap-3 rounded-lg border border-base-300 bg-base-100 px-3 py-2"
                    data-ui="group-discover-candidate"
                >
                    <div class="min-w-0 flex-1 space-y-2">
                        <x-user-badge :user="$candidate" />
                        @if ($matchTagLabels !== [] || filled($playAreaCaption))
                            <div class="flex flex-col gap-1.5" data-ui="group-discover-fit">
                                @if ($matchTagLabels !== [])
                                    <ul class="flex flex-wrap gap-1.5" role="list">
                                        @foreach ($matchTagLabels as $tagLabel)
                                            <li class="badge badge-outline badge-sm">{{ $tagLabel }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                                @if (filled($playAreaCaption))
                                    <p class="text-xs text-base-content/70">{{ $playAreaCaption }}</p>
                                @endif
                            </div>
                        @endif
                    </div>
                    @if ($canManage)
                        <div class="shrink-0 pt-0.5">
                            @if ($candidate->has_pending_group_invite)
                                <x-button
                                    type="button"
                                    class="btn-ghost btn-sm"
                                    disabled
                                    :label="__('ui.groups.discover_invited')"
                                />
                            @else
                                <x-button
                                    type="button"
                                    class="btn-primary btn-sm"
                                    wire:click="invite({{ $candidate->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="invite"
                                    icon="o-user-plus"
                                    :label="__('ui.groups.discover_invite')"
                                    :tooltip="__('ui.groups.discover_invite')"
                                    :aria-label="__('ui.groups.discover_invite')"
                                />
                            @endif
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>

        <div class="pt-2">
            {{ $candidates->links() }}
        </div>
    @endif
</div>
