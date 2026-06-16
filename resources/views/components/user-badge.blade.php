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
    'contactPopover' => true,
    'contactTooltip' => null,
])

@php
    use App\Models\User;

    $usesOrganization = $organization !== null;
    $resolvedName = trim((string) ($name ?? ($usesOrganization
        ? $organization?->name
        : ($user !== null ? $user->badgeDisplayName() : null)) ?? __('ui.common.unknown_user')));
    $resolvedAvatarUrl = is_string($avatarUrl) && $avatarUrl !== ''
        ? $avatarUrl
        : ($usesOrganization && $organization !== null
            ? $organization->logoUrl()
            : ($user !== null
                ? $user->avatarUrl()
                : User::uiAvatarsUrl($resolvedName, '#1d4ed8', '#ffffff', 2)));

    $avatarSizeClass = match ($size) {
        'sm' => 'h-8 w-8 text-xs',
        'lg' => 'h-11 w-11 text-base',
        default => 'h-9 w-9 text-sm',
    };
    $canRenderContactPopover = auth()->check() && $contactPopover && $user !== null && ! $usesOrganization;
    $resolvedContactTooltip = is_string($contactTooltip) && $contactTooltip !== ''
        ? $contactTooltip
        : __('ui.profile.contact_popover_tooltip');
@endphp

@if ($canRenderContactPopover)
    @php
        $containerClass = trim('inline-flex min-w-0 '.(string) ($attributes->get('class') ?? ''));
    @endphp
    <livewire:activities.user-badge-contact
        :user="$user"
        :size="$size"
        :name-class="$nameClass"
        :subline="$subline"
        :avatar-only="$avatarOnly"
        :track-nav-avatar="$trackNavAvatar"
        :contact-tooltip="$resolvedContactTooltip"
        :container-class="$containerClass"
        :key="'user-badge-contact-'.$user->id"
    />
@elseif ($avatarOnly)
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
