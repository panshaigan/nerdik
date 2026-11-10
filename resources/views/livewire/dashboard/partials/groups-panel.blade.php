@php
    /** @var list<array{group: \App\Models\Group, users_count: int, next_free_day: \Carbon\CarbonInterface|null}> $groupCards */
    /** @var \App\Models\Group|null $suggestedGroup */
    $groupCards = $groupCards ?? [];
    $suggestedGroup = $suggestedGroup ?? null;
    $suggestedMemberCount = $suggestedGroup !== null ? (int) ($suggestedGroup->users_count ?? 0) : 0;
@endphp

<div
    class="mt-4 rounded-2xl border border-base-300 bg-base-100 p-4 sm:p-6"
    data-ui="dashboard-groups-panel"
>
    @if ($groupCards !== [])
        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" role="list">
            @foreach ($groupCards as $card)
                @php($group = $card['group'])
                <li
                    wire:key="dashboard-group-{{ $group->id }}"
                    class="rounded-xl border border-base-300 bg-base-200/30 p-4"
                    data-ui="dashboard-group-card"
                >
                    <a
                        href="{{ route('groups.show', $group) }}"
                        wire:navigate
                        class="block font-medium text-base-content hover:text-primary"
                    >
                        {{ $group->name }}
                    </a>
                    @if (filled($group->description))
                        <p class="mt-2 line-clamp-2 text-sm text-base-content/70">{{ $group->description }}</p>
                    @endif
                    <p class="mt-2 text-sm text-base-content/70">
                        {{ trans_choice('ui.groups.member_count', $card['users_count'], ['count' => $card['users_count']]) }}
                    </p>
                    @if ($card['next_free_day'] !== null)
                        <p class="mt-2 text-xs text-base-content/55">
                            {{ __('ui.dashboard.next_group_free_day', ['date' => format_date_in_user_tz($card['next_free_day'])]) }}
                        </p>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <div class="text-center" data-ui="dashboard-groups-empty">
            <p class="text-sm text-base-content/70">{{ __('ui.dashboard.groups_empty') }}</p>

            @if ($suggestedGroup !== null)
                <div class="mt-6 text-left" data-ui="dashboard-group-suggested">
                    <p class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
                        {{ __('ui.dashboard.suggested_group') }}
                    </p>
                    <div class="mt-3 rounded-xl border border-base-300 bg-base-100 p-4">
                        <a
                            href="{{ route('groups.show', $suggestedGroup) }}"
                            wire:navigate
                            class="block font-medium text-base-content hover:text-primary"
                        >
                            {{ $suggestedGroup->name }}
                        </a>
                        <p class="mt-2 text-sm text-base-content/70">
                            {{ trans_choice('ui.groups.member_count', $suggestedMemberCount, ['count' => $suggestedMemberCount]) }}
                        </p>
                        @if (filled($suggestedGroup->description))
                            <p class="mt-2 line-clamp-2 text-sm text-base-content/60">{{ $suggestedGroup->description }}</p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="mt-4 flex flex-wrap gap-2 border-t border-base-300 pt-4">
        <x-button
            :link="route('groups.index')"
            wire:navigate
            class="btn-primary btn-sm"
            icon="o-magnifying-glass"
            :label="__('ui.dashboard.find_groups')"
            data-ui="dashboard-groups-find"
        />
    </div>
</div>
