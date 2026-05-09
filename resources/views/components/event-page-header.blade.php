@props([
    'title',
    'user' => null,
    'organization' => null,
    'hrIcon' => 'o-academic-cap',
    'headerClass' => '!mb-0 px-6 py-3 sm:px-10',
    'hrClass' => 'mt-1',
    'userBadgeSize' => 'md',
    'userBadgeTitle' => 'Organizer',
])
<div class="sm:px-4 lg:px-6 {{ $attributes->class([]) }}">
    <x-header :title="$title" :class="$headerClass" size="text-3xl sm:text-4xl" use-h1>
        <x-slot:title class="text-primary text-glow-primary">
            <span class="inline-flex flex-wrap items-center gap-x-3 gap-y-1">
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
