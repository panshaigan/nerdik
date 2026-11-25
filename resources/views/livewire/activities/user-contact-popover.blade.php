@php
    $hasContactSections = $contacts['email'] !== null
        || $contacts['facebook'] !== null
        || $contacts['discord'] !== null;
    $activityInviteOptions = $activityInviteOptions ?? [];
    $activityInviteCount = count($activityInviteOptions);
    $organizationInviteOptions = $organizationInviteOptions ?? [];
    $organizationInviteCount = count($organizationInviteOptions);
    $inviteCount = $activityInviteCount + $organizationInviteCount;
    $hasFooterActions = $targetUser !== null && (
        $inviteCount > 0
        || (($organizationJoinSubjectId ?? null) && ($organizationJoinRecipientId ?? null))
        || ($canBanTarget ?? false)
        || $canImpersonateTarget
    );
    $defaultTab = $canViewContact ? 'contact' : 'statistics';
@endphp

<div
    class="flex min-h-0 flex-1 flex-col"
    data-ui="user-contact-popover"
    data-overlay-sticky-footer
>
    <div class="flex min-h-0 flex-1 flex-col" data-ui="user-contact-popover-body">
        @if ($targetUser !== null)
            <x-ui.tabs-with-toolbar
                :selected="$defaultTab"
                label-bar-class="flex w-full min-w-0 items-center border-b border-base-300 gap-2"
                label-div-class="flex gap-5 px-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="relative flex min-h-0 w-full flex-1 flex-col"
                data-ui="overlay-sticky-tabs"
            >
                <x-slot:heading>
                    <div class="flex flex-col items-center px-4 pt-4 pb-3 text-center" data-ui="user-contact-popover-hero">
                        <div class="avatar">
                            <div class="h-28 w-28 shrink-0 overflow-hidden rounded-full border-2 border-base-300 bg-base-300 shadow-[0_0_24px_color-mix(in_oklch,var(--color-primary)_28%,transparent)]">
                                @php
                                    $heroPicture = $targetUser->avatarPicture(\App\Support\Ui\AvatarSlot::Hero);
                                @endphp
                                <img
                                    src="{{ $heroPicture->resolvedUrl($targetUser, \App\Support\Ui\AvatarSlot::Hero) }}"
                                    alt="{{ $targetUser->displayName() }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            </div>
                        </div>
                    </div>
                </x-slot:heading>

                <x-tab
                    name="contact"
                    :label="__('ui.profile.tab_contact')"
                    class="!p-0"
                    data-ui="user-contact-popover-tab-contact"
                    icon="o-envelope"
                >
                    <div class="space-y-3 p-4 text-sm" data-ui="user-contact-popover-contact">
                        @if ($canViewContact)
                            @if ($hasContactSections)
                                <div class="space-y-4">
                                    @if ($contacts['email'] !== null)
                                        <div class="space-y-2" data-ui="user-contact-popover-section-email">
                                            <p class="text-sm font-semibold text-base-content">{{ __('ui.profile.contact_section_email') }}</p>
                                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                                                <a class="btn btn-sm btn-primary w-full sm:w-auto" href="{{ $contacts['email']['mailto'] }}">{{ __('ui.profile.contact_email_compose') }}</a>
                                                <a class="btn btn-sm btn-outline w-full sm:w-auto" href="{{ $contacts['email']['gmail'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_email_gmail') }}</a>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-ghost w-full sm:w-auto"
                                                    x-on:click="window.copyToClipboard(@js($contacts['email']['address']), { message: @js(__('ui.common.copied')) })"
                                                >{{ __('ui.profile.contact_email_copy') }}</button>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($contacts['facebook'] !== null)
                                        <div class="space-y-2" data-ui="user-contact-popover-section-facebook">
                                            <p class="text-sm font-semibold text-base-content">{{ __('ui.profile.contact_section_facebook') }}</p>
                                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                                                <a class="btn btn-sm btn-outline w-full sm:w-auto" href="{{ $contacts['facebook']['profileUrl'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_facebook_profile') }}</a>
                                                @if (filled($contacts['facebook']['messagesUrl'] ?? null))
                                                    <a class="btn btn-sm btn-outline w-full sm:w-auto" href="{{ $contacts['facebook']['messagesUrl'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_facebook_message') }}</a>
                                                @endif
                                                @if (filled($contacts['facebook']['messengerUrl'] ?? null))
                                                    <a class="btn btn-sm btn-outline w-full sm:w-auto" href="{{ $contacts['facebook']['messengerUrl'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_messenger_message') }}</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if ($contacts['discord'] !== null)
                                        <div class="space-y-2" data-ui="user-contact-popover-section-discord">
                                            <p class="text-sm font-semibold text-base-content">{{ __('ui.profile.contact_section_discord') }}</p>
                                            <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                                                <a class="btn btn-sm btn-outline w-full sm:w-auto" href="{{ $contacts['discord']['webUrl'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_discord_web') }}</a>
                                                <a class="btn btn-sm btn-outline w-full sm:w-auto" href="{{ $contacts['discord']['appUrl'] }}">{{ __('ui.profile.contact_discord_app') }}</a>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <p class="text-base-content/60">{{ __('ui.profile.contact_methods_empty') }}</p>
                            @endif
                        @else
                            <p class="text-base-content/60">{{ __('ui.profile.contact_methods_empty') }}</p>
                        @endif
                    </div>
                </x-tab>

                <x-tab
                    name="play-preferences"
                    :label="__('ui.profile.preview_play_preferences_tab')"
                    class="!p-0"
                    data-ui="user-contact-popover-tab-play-preferences"
                    icon="o-map-pin"
                >
                    <div class="space-y-5 p-4 text-sm" data-ui="user-contact-popover-play-preferences">
                        <div class="space-y-2" data-ui="user-contact-popover-play-area">
                            <p class="text-sm font-semibold text-base-content">{{ __('ui.profile.availability.play_area_title') }}</p>
                            @if ($playAreaPreview !== null)
                                @php
                                    $playAreaPreviewMapConfig = [
                                        'latitude' => $playAreaPreview['latitude'],
                                        'longitude' => $playAreaPreview['longitude'],
                                        'radiusKm' => $playAreaPreview['radius_km'],
                                    ];
                                @endphp
                                <p class="text-base-content/70">{{ $playAreaPreview['caption'] }}</p>
                                <div
                                    data-user-play-area-preview-map
                                    class="relative z-0 overflow-visible rounded-md border border-base-300"
                                    data-latitude="{{ $playAreaPreview['latitude'] }}"
                                    data-longitude="{{ $playAreaPreview['longitude'] }}"
                                    data-radius-km="{{ $playAreaPreview['radius_km'] }}"
                                    wire:ignore
                                >
                                    <script type="application/json" data-upapm-config>@json($playAreaPreviewMapConfig)</script>
                                    <div
                                        data-upapm-map
                                        class="relative z-0 w-full rounded-md"
                                        style="height: 280px;"
                                        data-ui="user-contact-popover-play-area-map"
                                    ></div>
                                </div>
                            @else
                                <p class="text-sm text-base-content/50">{{ __('ui.profile.availability.play_area_empty') }}</p>
                            @endif
                        </div>

                        <div class="space-y-2" data-ui="user-contact-popover-interest-tags">
                            <p class="text-sm font-semibold text-base-content">{{ __('ui.profile.availability.interest_tags_title') }}</p>
                            @if ($interestTagBadges !== [])
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($interestTagBadges as $tagBadge)
                                        <span
                                            class="badge badge-outline badge-sm"
                                            wire:key="user-preview-interest-tag-{{ $tagBadge['id'] }}"
                                        >{{ $tagBadge['badge'] }}</span>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-sm text-base-content/50">{{ __('ui.profile.availability.interest_tags_empty') }}</p>
                            @endif
                        </div>
                    </div>
                </x-tab>

                <x-tab
                    name="statistics"
                    :label="__('ui.organizations.statistics_tab')"
                    class="!p-0"
                    data-ui="user-contact-popover-tab-statistics"
                    icon="o-chart-bar"
                >
                    <div class="space-y-5 p-4 text-sm" data-ui="user-contact-popover-statistics">
                        <div class="space-y-2" data-ui="user-contact-popover-hosted-stats">
                            <p class="text-sm font-semibold text-base-content">{{ __('ui.profile.contact_hosted_section') }}</p>
                            @forelse ($hostedStatsByType as $stat)
                                <div class="flex items-center justify-between gap-3 text-sm text-base-content/65">
                                    <span>{{ $stat['label'] }}</span>
                                    <span class="font-semibold tabular-nums text-base-content">{{ $stat['count'] }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-base-content/50">{{ __('ui.profile.contact_no_hosted_activities') }}</p>
                            @endforelse
                        </div>

                        <div class="space-y-2" data-ui="user-contact-popover-participation-stats">
                            <p class="text-sm font-semibold text-base-content">{{ __('ui.profile.contact_participation_section') }}</p>
                            @forelse ($participationStatsByType as $stat)
                                <div class="flex items-center justify-between gap-3 text-sm text-base-content/65">
                                    <span>{{ $stat['label'] }}</span>
                                    <span class="font-semibold tabular-nums text-base-content">{{ $stat['count'] }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-base-content/50">{{ __('ui.profile.contact_no_played_activities') }}</p>
                            @endforelse
                        </div>

                        <div class="space-y-2" data-ui="user-contact-popover-reputation-stats">
                            <p class="text-sm font-semibold text-base-content">{{ __('ui.profile.contact_reputation_section') }}</p>
                            <div class="flex items-center justify-between gap-3 text-sm text-base-content/65">
                                <span>{{ __('ui.profile.contact_host_bans_stat') }}</span>
                                <span class="font-semibold tabular-nums text-base-content" data-ui="user-contact-popover-host-ban-count">{{ $hostBanCount }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-sm text-base-content/65">
                                <span>{{ __('ui.profile.contact_absences_stat') }}</span>
                                <span class="font-semibold tabular-nums text-base-content" data-ui="user-contact-popover-absence-count">{{ $absenceCount }}</span>
                            </div>
                        </div>
                    </div>
                </x-tab>
            </x-ui.tabs-with-toolbar>
        @endif
    </div>

    @if ($hasFooterActions)
        <div class="flex shrink-0 flex-wrap gap-2 border-t border-base-300 px-4 py-3" data-ui="user-contact-popover-requests">
            @if ($inviteCount === 1)
                @php
                    $singleInviteKind = $activityInviteCount === 1 ? 'activity' : 'organization';
                    $singleInviteId = $singleInviteKind === 'activity'
                        ? $activityInviteOptions[0]['id']
                        : $organizationInviteOptions[0]['id'];
                @endphp
                <x-button
                    type="button"
                    class="btn-sm btn-outline"
                    wire:click="openInvite('{{ $singleInviteKind }}', {{ $singleInviteId }})"
                    data-ui="user-contact-popover-invite"
                    :aria-label="__('ui.user_requests.invite')"
                >{{ __('ui.user_requests.invite') }}</x-button>
            @elseif ($inviteCount > 1)
                <div class="dropdown dropdown-top" data-ui="user-contact-popover-invite-menu">
                    <div
                        tabindex="0"
                        role="button"
                        class="btn btn-sm btn-outline"
                        aria-haspopup="menu"
                        aria-label="{{ __('ui.user_requests.invite') }}"
                    >
                        {{ __('ui.user_requests.invite') }}
                        <x-icon name="o-chevron-up" class="h-4 w-4 opacity-70" />
                    </div>
                    <ul
                        tabindex="0"
                        role="menu"
                        class="dropdown-content menu z-[100] mb-1 w-64 rounded-box border border-base-300 bg-base-100 p-1 shadow light:border-neutral"
                        data-ui="user-contact-popover-invite-menu-list"
                    >
                        @if ($activityInviteCount > 0)
                            <li class="menu-title" role="presentation">
                                <span>{{ __('ui.user_requests.invite_activities_section') }}</span>
                            </li>
                            @foreach ($activityInviteOptions as $activityInviteOption)
                                <li wire:key="activity-invite-option-{{ $activityInviteOption['id'] }}" role="none">
                                    <button
                                        type="button"
                                        role="menuitem"
                                        wire:click="openInvite('activity', {{ $activityInviteOption['id'] }})"
                                    >
                                        {{ $activityInviteOption['name'] }}
                                    </button>
                                </li>
                            @endforeach
                        @endif
                        @if ($organizationInviteCount > 0)
                            <li @class(['menu-title', 'mt-1 border-t border-base-300 pt-2 light:border-neutral' => $activityInviteCount > 0]) role="presentation">
                                <span>{{ __('ui.user_requests.invite_organizations_section') }}</span>
                            </li>
                            @foreach ($organizationInviteOptions as $organizationInviteOption)
                                <li wire:key="org-invite-option-{{ $organizationInviteOption['id'] }}" role="none">
                                    <button
                                        type="button"
                                        role="menuitem"
                                        wire:click="openInvite('organization', {{ $organizationInviteOption['id'] }})"
                                    >
                                        {{ $organizationInviteOption['name'] }}
                                    </button>
                                </li>
                            @endforeach
                        @endif
                    </ul>
                </div>
            @endif
            @if ($canBanTarget)
                @if ($isBannedByViewer)
                    <x-button
                        type="button"
                        class="btn-sm btn-outline"
                        wire:click="unbanUser"
                        spinner="unbanUser"
                        data-ui="user-contact-popover-unban"
                        :aria-label="__('ui.profile.unban_user')"
                    >{{ __('ui.profile.unban_user') }}</x-button>
                @else
                    <x-button
                        type="button"
                        class="btn-sm btn-outline btn-error"
                        wire:click="banUser"
                        spinner="banUser"
                        data-ui="user-contact-popover-ban"
                        :aria-label="__('ui.profile.ban_user')"
                    >{{ __('ui.profile.ban_user') }}</x-button>
                @endif
            @endif
            @if ($organizationJoinSubjectId && $organizationJoinRecipientId)
                <livewire:user-requests.send-user-request
                    type="organization_join_request"
                    subject-type="organization"
                    :subject-id="$organizationJoinSubjectId"
                    :recipient-id="$organizationJoinRecipientId"
                    :key="'organization-join-'.$targetUser->id.'-'.$organizationJoinSubjectId"
                />
            @endif
            @if ($canImpersonateTarget)
                <button
                    type="button"
                    wire:click="impersonate"
                    class="btn btn-sm btn-warning"
                    data-ui="user-contact-popover-impersonate"
                >{{ __('ui.impersonation.log_in_as') }}</button>
            @endif
        </div>
        @foreach ($activityInviteOptions as $activityInviteOption)
            <livewire:user-requests.send-user-request
                type="activity_invite"
                subject-type="activity"
                :subject-id="$activityInviteOption['id']"
                :recipient-id="$targetUser->id"
                :show-trigger="false"
                :key="'activity-invite-'.$targetUser->id.'-'.$activityInviteOption['id']"
            />
        @endforeach
        @foreach ($organizationInviteOptions as $organizationInviteOption)
            <livewire:user-requests.send-user-request
                type="organization_invite"
                subject-type="organization"
                :subject-id="$organizationInviteOption['id']"
                :recipient-id="$targetUser->id"
                :show-trigger="false"
                :key="'organization-invite-'.$targetUser->id.'-'.$organizationInviteOption['id']"
            />
        @endforeach
    @endif
</div>
