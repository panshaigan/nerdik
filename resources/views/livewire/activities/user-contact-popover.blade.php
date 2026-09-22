@php
    $hasContactSections = $contacts['email'] !== null
        || $contacts['facebook'] !== null
        || $contacts['discord'] !== null;
    $hasFooterActions = $targetUser !== null && (
        ($activityInviteSubjectId ?? null)
        || ($organizationInviteSubjectId ?? null)
        || (($organizationJoinSubjectId ?? null) && ($organizationJoinRecipientId ?? null))
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
                label-div-class="flex gap-5 overflow-x-auto px-1"
                label-class="tab tab-lifted tab-md !px-0 !py-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
                active-class="!text-base-content border-b border-primary text-primary"
                tabs-class="relative flex min-h-0 w-full flex-1 flex-col"
                data-ui="overlay-sticky-tabs"
            >
                <x-slot:heading>
                    <div class="flex flex-col items-center gap-3 px-4 pt-4 pb-3 text-center" data-ui="user-contact-popover-hero">
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
                        <div class="min-w-0 space-y-2">
                            <p class="truncate text-lg font-semibold text-base-content">{{ $targetUser->displayName() }}</p>
                            @if ($targetUser->organization !== null)
                                @php
                                    $organization = $targetUser->organization;
                                    $orgLabel = filled($organization->acronym)
                                        ? (string) $organization->acronym
                                        : (string) $organization->name;
                                @endphp
                                <div class="flex justify-center" data-ui="user-contact-popover-organization">
                                    <livewire:activities.organization-badge-contact
                                        :organization="$organization"
                                        :name="$orgLabel"
                                        size="sm"
                                        name-class="truncate text-xs font-medium text-base-content/80"
                                        container-class="inline-flex max-w-full min-w-0"
                                        :key="'user-contact-org-'.$organization->id.'-'.$targetUser->id"
                                    />
                                </div>
                            @endif
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
                                            <p class="text-xs font-medium text-base-content/70">{{ __('ui.profile.contact_section_email') }}</p>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a class="btn btn-xs btn-primary" href="{{ $contacts['email']['mailto'] }}">{{ __('ui.profile.contact_email_compose') }}</a>
                                                <a class="btn btn-xs btn-outline" href="{{ $contacts['email']['gmail'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_email_gmail') }}</a>
                                                <button
                                                    type="button"
                                                    class="btn btn-xs btn-ghost"
                                                    x-on:click="window.copyToClipboard(@js($contacts['email']['address']), { message: @js(__('ui.common.copied')) })"
                                                >{{ __('ui.profile.contact_email_copy') }}</button>
                                            </div>
                                        </div>
                                    @endif

                                    @if ($contacts['facebook'] !== null)
                                        <div class="space-y-2" data-ui="user-contact-popover-section-facebook">
                                            <p class="text-xs font-medium text-base-content/70">{{ __('ui.profile.contact_section_facebook') }}</p>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a class="btn btn-xs btn-outline" href="{{ $contacts['facebook']['profileUrl'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_facebook_profile') }}</a>
                                                @if (filled($contacts['facebook']['messagesUrl'] ?? null))
                                                    <a class="btn btn-xs btn-outline" href="{{ $contacts['facebook']['messagesUrl'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_facebook_message') }}</a>
                                                @endif
                                                @if (filled($contacts['facebook']['messengerUrl'] ?? null))
                                                    <a class="btn btn-xs btn-outline" href="{{ $contacts['facebook']['messengerUrl'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_messenger_message') }}</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if ($contacts['discord'] !== null)
                                        <div class="space-y-2" data-ui="user-contact-popover-section-discord">
                                            <p class="text-xs font-medium text-base-content/70">{{ __('ui.profile.contact_section_discord') }}</p>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a class="btn btn-xs btn-outline" href="{{ $contacts['discord']['webUrl'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_discord_web') }}</a>
                                                <a class="btn btn-xs btn-outline" href="{{ $contacts['discord']['appUrl'] }}">{{ __('ui.profile.contact_discord_app') }}</a>
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
                    </div>
                </x-tab>
            </x-ui.tabs-with-toolbar>
        @endif
    </div>

    @if ($hasFooterActions)
        <div class="flex shrink-0 flex-wrap gap-2 border-t border-base-300 px-4 py-3" data-ui="user-contact-popover-requests">
            @if ($activityInviteSubjectId)
                <livewire:user-requests.send-user-request
                    type="activity_invite"
                    subject-type="activity"
                    :subject-id="$activityInviteSubjectId"
                    :recipient-id="$targetUser->id"
                    :key="'activity-invite-'.$targetUser->id.'-'.$activityInviteSubjectId"
                />
            @endif
            @if ($organizationInviteSubjectId)
                <livewire:user-requests.send-user-request
                    type="organization_invite"
                    subject-type="organization"
                    :subject-id="$organizationInviteSubjectId"
                    :recipient-id="$targetUser->id"
                    :key="'organization-invite-'.$targetUser->id.'-'.$organizationInviteSubjectId"
                />
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
    @endif
</div>
