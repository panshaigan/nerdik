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
    'contactWireKey' => null,
    'lateMinutes' => null,
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
    // Keep nested Livewire badge instances unique when the same host appears on a listing
    // card and again inside a preview modal on the same page (duplicate keys mis-route openModal).
    $contactWireKeySuffix = is_string($contactWireKey) && $contactWireKey !== ''
        ? '-'.$contactWireKey
        : '';

    $resolvedNameClass = $nameClass !== ''
        ? $nameClass
        : 'truncate text-sm font-semibold text-base-content';

    // Prefer CSS ellipsis when space is tight; only force truncate when the caller
    // did not opt into wrapping (e.g. page-header on small screens).
    if (
        ! str_contains($resolvedNameClass, 'truncate')
        && ! str_contains($resolvedNameClass, 'whitespace-normal')
        && ! str_contains($resolvedNameClass, 'line-clamp-')
    ) {
        $resolvedNameClass = 'truncate '.$resolvedNameClass;
    }

    $resolvedLateMinutes = $lateMinutes !== null ? (int) $lateMinutes : null;
    $showLateBadge = $resolvedLateMinutes !== null && $resolvedLateMinutes > 0;
    $lateBadgeLabel = $showLateBadge
        ? ($resolvedLateMinutes > 99 ? '99+' : '+'.$resolvedLateMinutes)
        : null;
    $lateBadgeTip = $showLateBadge
        ? __('ui.activities.late_badge_tooltip', ['minutes' => $resolvedLateMinutes])
        : null;
@endphp

@if ($canRenderOrganizationPopover)
    @php
        $containerClass = trim('inline-flex min-w-0 max-w-full overflow-hidden '.(string) ($attributes->get('class') ?? ''));
    @endphp
    <livewire:activities.organization-badge-contact
        :organization="$organization"
        :user="$user"
        :name="$name"
        :size="$size"
        :name-class="$resolvedNameClass"
        :subline="$subline"
        :avatar-only="$avatarOnly"
        :track-nav-avatar="$trackNavAvatar"
        :contact-tooltip="$resolvedOrganizationTooltip"
        :container-class="$containerClass"
        :late-minutes="$resolvedLateMinutes"
        :key="'organization-badge-contact-'.$organization->id.'-late-'.($resolvedLateMinutes ?? '0').$contactWireKeySuffix"
    />
@elseif ($canRenderContactPopover)
    @php
        $containerClass = trim('inline-flex min-w-0 max-w-full overflow-hidden '.(string) ($attributes->get('class') ?? ''));
    @endphp
    <livewire:activities.user-badge-contact
        :user="$user"
        :size="$size"
        :name-class="$resolvedNameClass"
        :subline="$subline"
        :avatar-only="$avatarOnly"
        :track-nav-avatar="$trackNavAvatar"
        :contact-tooltip="$resolvedContactTooltip"
        :container-class="$containerClass"
        :context-activity-id="$contextActivityId"
        :context-organization-id="$contextOrganizationId"
        :late-minutes="$resolvedLateMinutes"
        :key="'user-badge-contact-'.$user->id.'-'.($contextActivityId ?? '0').'-'.($contextOrganizationId ?? '0').'-late-'.($resolvedLateMinutes ?? '0').$contactWireKeySuffix"
    />
@elseif ($avatarOnly)
    <div {{ $attributes->class('avatar relative inline-flex') }}>
        <div class="{{ $avatarSizeClass }} relative shrink-0 overflow-hidden rounded-full border border-base-300 bg-base-300 text-base-content/80 light:border-neutral light:bg-neutral">
            <img
                src="{{ $resolvedAvatarUrl }}"
                alt="{{ $resolvedName }}"
                class="h-full w-full object-cover"
                loading="lazy"
                @if ($trackNavAvatar) data-nav-user-avatar @endif
            />
        </div>
        @if ($showLateBadge)
            <span
                class="pointer-events-none absolute -right-1 -top-1 z-[1] flex h-4 min-w-4 items-center justify-center rounded-full bg-warning px-0.5 text-[9px] font-semibold leading-none text-warning-content tooltip tooltip-top"
                data-tip="{{ $lateBadgeTip }}"
                data-ui="user-badge-late"
            >{{ $lateBadgeLabel }}</span>
        @endif
    </div>
@else
    <div {{ $attributes->class('flex max-w-full min-w-0 items-center gap-2 overflow-hidden') }}>
        <div class="avatar relative shrink-0">
            <div class="{{ $avatarSizeClass }} shrink-0 overflow-hidden rounded-full border border-base-300 bg-base-300 text-base-content/80 light:border-neutral light:bg-neutral">
                <img
                    src="{{ $resolvedAvatarUrl }}"
                    alt="{{ $resolvedName }}"
                    class="h-full w-full object-cover"
                    loading="lazy"
                    @if ($trackNavAvatar) data-nav-user-avatar @endif
                />
            </div>
            @if ($showLateBadge)
                <span
                    class="pointer-events-none absolute -right-1 -top-1 z-[1] flex h-4 min-w-4 items-center justify-center rounded-full bg-warning px-0.5 text-[9px] font-semibold leading-none text-warning-content tooltip tooltip-top"
                    data-tip="{{ $lateBadgeTip }}"
                    data-ui="user-badge-late"
                >{{ $lateBadgeLabel }}</span>
            @endif
        </div>
        <div class="min-w-0 flex-1 overflow-hidden">
            <p class="{{ $resolvedNameClass }}" title="{{ $resolvedName }}">{{ $resolvedName }}</p>
            @if ($subline)
                <p class="truncate text-xs text-base-content/65" title="{{ $subline }}">{{ $subline }}</p>
            @endif
        </div>
    </div>
@endif
