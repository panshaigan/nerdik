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
    'contextActivityId' => null,
    'contextOrganizationId' => null,
])

@php
    use App\Models\User;
    use App\Support\Ui\AvatarPicture;
    use App\Support\Ui\AvatarSlot;

    $usesOrganization = $organization !== null;
    $resolvedName = trim((string) ($name ?? ($usesOrganization
        ? $organization?->name
        : ($user !== null ? $user->badgeDisplayName() : null)) ?? __('ui.common.unknown_user')));

    $avatarPicture = $user !== null && ! $usesOrganization
        ? $user->avatarPicture(AvatarSlot::Badge)
        : null;

    $resolvedAvatarUrl = is_string($avatarUrl) && $avatarUrl !== ''
        ? $avatarUrl
        : ($avatarPicture !== null
            ? $avatarPicture->resolvedUrl($user, AvatarSlot::Badge)
            : ($usesOrganization && $organization !== null
                ? $organization->logoUrl()
                : ($user !== null
                    ? $user->avatarUrl(AvatarSlot::Badge)
                    : User::uiAvatarsUrl($resolvedName, '#1d4ed8', '#ffffff', 2, AvatarSlot::Badge->displaySize()))));

    $avatarSizeClass = match ($size) {
        'sm' => 'h-8 w-8 text-xs',
        'lg' => 'h-11 w-11 text-base',
        default => 'h-9 w-9 text-sm',
    };
    $canRenderOrganizationPopover = auth()->check() && $contactPopover && $organization !== null;
    $canRenderContactPopover = auth()->check() && $contactPopover && $user !== null && ! $usesOrganization;
    $resolvedContactTooltip = is_string($contactTooltip) && $contactTooltip !== ''
        ? $contactTooltip
        : __('ui.common.click_for_details');
    $resolvedOrganizationTooltip = is_string($contactTooltip) && $contactTooltip !== ''
        ? $contactTooltip
        : __('ui.common.click_for_details');
@endphp

@if ($canRenderOrganizationPopover)
    @php
        $containerClass = trim('inline-flex min-w-0 overflow-visible '.(string) ($attributes->get('class') ?? ''));
    @endphp
    <livewire:activities.organization-badge-contact
        :organization="$organization"
        :user="$user"
        :size="$size"
        :name-class="$nameClass"
        :subline="$subline"
        :avatar-only="$avatarOnly"
        :track-nav-avatar="$trackNavAvatar"
        :contact-tooltip="$resolvedOrganizationTooltip"
        :container-class="$containerClass"
        :key="'organization-badge-contact-'.$organization->id"
    />
@elseif ($canRenderContactPopover)
    @php
        $containerClass = trim('inline-flex min-w-0 overflow-visible '.(string) ($attributes->get('class') ?? ''));
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
        :context-activity-id="$contextActivityId"
        :context-organization-id="$contextOrganizationId"
        :key="'user-badge-contact-'.$user->id.'-'.($contextActivityId ?? '0').'-'.($contextOrganizationId ?? '0')"
    />
@elseif ($avatarOnly)
    <div {{ $attributes->class('avatar') }}>
        <div class="{{ $avatarSizeClass }} shrink-0 overflow-hidden rounded-full border border-base-300 bg-base-300 text-base-content/80 light:border-neutral light:bg-neutral">
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
    <div {{ $attributes->class('flex items-center gap-2 min-w-0 overflow-visible') }}>
        <div class="avatar">
            <div class="{{ $avatarSizeClass }} shrink-0 overflow-hidden rounded-full border border-base-300 bg-base-300 text-base-content/80 light:border-neutral light:bg-neutral">
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
