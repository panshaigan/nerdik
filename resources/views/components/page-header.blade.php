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
<div {{ $attributes->class(['px-6 py-5', $headerClass]) }}>
    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="font-display text-3xl font-medium leading-tight text-base text-glow-primary sm:text-4xl">
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
                name-class="font-display truncate text-sm font-normal text-base"
                class="shrink-0 [&_.avatar>div]:border-base/60 [&_.avatar>div]:bg-base-100/80 [&_.avatar>div]:box-glow-base"
            />
        @endif
    </div>

    @isset($subtitle)
        <div class="text-xs font-semibold uppercase tracking-widest text-base/75 text-glow-accent pl-1">
            {{ $subtitle }}
        </div>
    @endisset

    <x-ui.hr
        :icon="$hrIcon"
        show-end-glow
        :class="trim(''.$hrClass)"
        wrapper-class="flex items-center gap-2"
        edge-icon-class="size-3 text-primary/80"
        line-glow-class="pointer-events-none absolute left-1/2 top-1/2 h-3 w-32 -translate-x-1/2 -translate-y-1/2 bg-primary/55 blur-lg"
        center-class="grid place-items-center rounded-full border border-primary/60 bg-base-100/70 text-primary box-glow-primary"
        center-size-class="size-9"
    />
</div>
