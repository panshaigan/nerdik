@props([
    'title',
    'backUrl' => null,
    'user' => null,
    'organization' => null,
    'hrIcon' => 'o-sparkles',
    'headerClass' => '!mb-0 pb-1',
    'hrClass' => 'mt-1',
    'userBadgeSize' => 'md',
    'userBadgeTitle' => 'Organizer',
])
<div class="px-6 py-6 {{ $attributes->class([]) }}">
    <x-header :title="$title" :class="$headerClass" size="text-3xl sm:text-4xl" use-h1>
        <x-slot:title class="text-primary text-glow-primary">
            <span class="inline-flex flex-wrap items-center gap-x-3 gap-y-1">
                @if ($backUrl)
                    <a
                        href="{{ $backUrl }}"
                        class="btn btn-ghost btn-square shrink-0"
                        wire:navigate
                        aria-label="{{ __('ui.common.back') }}"
                        data-ui="page-header-back"
                    >
                        <x-icon name="o-chevron-double-left" class="h-8 w-8 shrink-0" />
                    </a>
                @endif
                <span>{{ $title }}</span>
                @isset($titleSuffix)
                    {{ $titleSuffix }}
                @endisset
            </span>
        </x-slot:title>

        @isset($subtitle)
            <x-slot:subtitle>
                {{ $subtitle }}
            </x-slot:subtitle>
        @endisset

        @if ($user)
            <x-slot:actions>
                <x-user-badge
                    :user="$user"
                    :organization="$organization"
                    :size="$userBadgeSize"
                    data-ui="activity-show-host"
                    :title="$userBadgeTitle"
                />
            </x-slot:actions>
        @endif
    </x-header>

    <x-ui.hr :icon="$hrIcon" :class="$hrClass" double />
</div>
