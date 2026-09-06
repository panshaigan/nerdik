@props([
    'title',
    'backUrl' => null,
    'user' => null,
    'organization' => null,
    'hrIcon' => 'o-sparkles',
    'headerClass' => '',
    'hrClass' => '',
    'userBadgeSize' => 'md',
    'userBadgeTitle' => null,
])
@php
    $resolvedUserBadgeTitle = $userBadgeTitle ?? __('ui.events.host');
@endphp
<div {{ $attributes->class(['px-4 py-5 sm:px-6 lg:px-8', $headerClass]) }}>
    <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
        <div class="min-w-0 w-full">
            <h1 class="font-display text-3xl font-medium leading-tight text-base text-glow-base-100 sm:text-4xl">
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
            </h1>
        </div>

        @if ($user)
            <x-user-badge
                :user="$user"
                :organization="$organization"
                :size="$userBadgeSize"
                data-ui="activity-show-host"
                :title="$resolvedUserBadgeTitle"
                name-class="font-display text-sm font-normal text-base max-sm:whitespace-normal sm:truncate"
                class="w-full sm:w-auto sm:shrink-0 [&_.avatar>div]:border-base/60 [&_.avatar>div]:bg-base-100/80 [&_.avatar>div]:box-glow-base"
            />
        @endif
    </div>

    @isset($subtitle)
        <div class="mt-1 text-sm text-base-content/60 pl-1">
            {{ $subtitle }}
        </div>
    @endisset

    <x-ui.hr
        :icon="$hrIcon"
        show-end-glow
        :class="trim(''.$hrClass)"
        wrapper-class="mt-3 flex items-center gap-2"
        center-size-class="size-9"
    />
</div>
