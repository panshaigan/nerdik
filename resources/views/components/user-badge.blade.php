@props([
    'user' => null,
    'organization' => null,
    'name' => null,
    'avatarPath' => null,
    'avatarUrl' => null,
    'size' => 'md',
    'nameClass' => '',
    'subline' => null,
    'avatarOnly' => false,
    'trackNavAvatar' => false,
])

@php
    use App\Models\User;

    $usesOrganization = $organization !== null;
    $resolvedName = trim((string) ($name ?? $organization?->name ?? $user?->displayName() ?? __('ui.common.unknown_user')));
    $avatarBackgroundColor = (string) ($user?->profile?->avatar_bg_color ?? '#1d4ed8');
    $avatarTextColor = (string) ($user?->profile?->avatar_text_color ?? '#ffffff');
    $resolvedAvatarUrl = is_string($avatarUrl) && $avatarUrl !== ''
        ? $avatarUrl
        : (! $usesOrganization && $user !== null
            ? $user->avatarUrl()
            : User::uiAvatarsUrl($resolvedName, $avatarBackgroundColor, $avatarTextColor, 2));

    $avatarSizeClass = match ($size) {
        'sm' => 'h-8 w-8 text-xs',
        'lg' => 'h-11 w-11 text-base',
        default => 'h-9 w-9 text-sm',
    };
@endphp

@if ($avatarOnly)
    <div {{ $attributes->class('avatar') }}>
        <div class="{{ $avatarSizeClass }} shrink-0 overflow-hidden rounded-full border border-base-300 bg-base-300 text-base-content/80">
            <img
                src="{{ $resolvedAvatarUrl }}"
                alt="{{ $resolvedName }}"
                class="h-full w-full object-cover"
                loading="lazy"
                @if ($trackNavAvatar) data-nav-user-avatar @endif
            />
        </div>
    </div>
@else
    <div {{ $attributes->class('flex items-center gap-2 min-w-0') }}>
        <div class="avatar">
            <div class="{{ $avatarSizeClass }} shrink-0 overflow-hidden rounded-full border border-base-300 bg-base-300 text-base-content/80">
                <img
                    src="{{ $resolvedAvatarUrl }}"
                    alt="{{ $resolvedName }}"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    @if ($trackNavAvatar) data-nav-user-avatar @endif
                />
            </div>
        </div>
        <div class="min-w-0">
            <p class="{{ $nameClass !== '' ? $nameClass : 'truncate text-sm font-semibold text-base-content' }}">{{ $resolvedName }}</p>
            @if ($subline)
                <p class="truncate text-xs text-base-content/65">{{ $subline }}</p>
            @endif
        </div>
    </div>
@endif
