@php
    /** @var list<array{group: \App\Models\Group, users_count: int, next_free_day: \Carbon\CarbonInterface|null}> $groupCards */
    /** @var \App\Models\Group|null $suggestedGroup */
    $groupCards = $groupCards ?? [];
    $suggestedGroup = $suggestedGroup ?? null;
@endphp

<section class="space-y-4" data-ui="dashboard-groups-strip">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
            {{ __('ui.dashboard.my_groups') }}
        </h2>
        <a
            href="{{ route('groups.index') }}"
            wire:navigate
            class="text-sm text-primary hover:underline"
            data-ui="dashboard-groups-find"
        >
            {{ __('ui.dashboard.find_groups') }}
        </a>
    </div>

    @if ($groupCards !== [])
        <ul class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4" role="list">
            @foreach ($groupCards as $card)
                @php($group = $card['group'])
                <li
                    wire:key="dashboard-group-{{ $group->id }}"
                    class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"
                    data-ui="dashboard-group-card"
                >
                    <a
                        href="{{ route('groups.show', $group) }}"
                        wire:navigate
                        class="block font-medium text-base-content hover:text-primary"
                    >
                        {{ $group->name }}
                    </a>
                    <p class="mt-1 text-sm text-base-content/70">
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
        <p class="text-sm text-base-content/70">{{ __('ui.dashboard.groups_empty') }}</p>

        @if ($suggestedGroup !== null)
            <h3 class="text-xs font-semibold uppercase tracking-wide text-base-content/55">
                {{ __('ui.dashboard.suggested_group') }}
            </h3>
            <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm" data-ui="dashboard-group-suggested">
                <a
                    href="{{ route('groups.show', $suggestedGroup) }}"
                    wire:navigate
                    class="block font-medium text-base-content hover:text-primary"
                >
                    {{ $suggestedGroup->name }}
                </a>
            </div>
        @endif
    @endif
</section>
