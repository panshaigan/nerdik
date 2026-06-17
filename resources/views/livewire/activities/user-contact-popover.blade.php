@php
    $hasContactSections = $contacts['email'] !== null
        || $contacts['facebook'] !== null
        || $contacts['discord'] !== null;
@endphp

<div class="min-w-0 space-y-5 overflow-x-hidden p-4 text-sm" data-ui="user-contact-popover">
    @if ($targetUser !== null)
        <div class="flex flex-col items-center gap-3 text-center" data-ui="user-contact-popover-hero">
            <div class="avatar">
                <div class="h-28 w-28 shrink-0 overflow-hidden rounded-full border-2 border-base-300 bg-base-300 shadow-[0_0_24px_color-mix(in_oklch,var(--color-primary)_28%,transparent)]">
                    <img
                        src="{{ $targetUser->avatarUrl() }}"
                        alt="{{ $targetUser->displayName() }}"
                        class="h-full w-full object-cover"
                        loading="lazy"
                    />
                </div>
            </div>
            <div class="min-w-0 space-y-2">
                @if ($targetUser->organization !== null)
                    @php
                        $organization = $targetUser->organization;
                        $orgLabel = filled($organization->acronym)
                            ? (string) $organization->acronym
                            : (string) $organization->name;
                    @endphp
                    <div
                        class="inline-flex max-w-full items-center gap-2 rounded-full border border-base-300 bg-base-200/60 px-3 py-1"
                        data-ui="user-contact-popover-organization"
                    >
                        <div class="avatar">
                            <div class="h-6 w-6 shrink-0 overflow-hidden rounded-full border border-base-300 bg-base-300">
                                <img
                                    src="{{ $organization->logoUrl() }}"
                                    alt="{{ $orgLabel }}"
                                    class="h-full w-full object-cover"
                                    loading="lazy"
                                />
                            </div>
                        </div>
                        <span class="truncate text-xs font-medium text-base-content/80">{{ $orgLabel }}</span>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="space-y-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.profile.contact_stats_title') }}</p>

        <div class="space-y-2" data-ui="user-contact-popover-hosted-stats">
            <p class="text-xs font-medium text-base-content/70">{{ __('ui.profile.contact_hosted_section') }}</p>
            @forelse ($hostedStatsByType as $stat)
                <div class="flex items-center justify-between text-base-content/80">
                    <span>{{ __('ui.profile.contact_hosted_type', ['type' => $stat['label']]) }}</span>
                    <span class="font-semibold text-base-content">{{ $stat['count'] }}</span>
                </div>
            @empty
                <p class="text-base-content/60">{{ __('ui.profile.contact_no_hosted_activities') }}</p>
            @endforelse
        </div>

        <div class="space-y-2" data-ui="user-contact-popover-participation-stats">
            <p class="text-xs font-medium text-base-content/70">{{ __('ui.profile.contact_participations_section') }}</p>
            @forelse ($participationStatsByType as $stat)
                <div class="flex items-center justify-between text-base-content/80">
                    <span>{{ __('ui.profile.contact_participation_type', ['type' => $stat['label']]) }}</span>
                    <span class="font-semibold text-base-content">{{ $stat['count'] }}</span>
                </div>
            @empty
                <p class="text-base-content/60">{{ __('ui.profile.contact_no_played_activities') }}</p>
            @endforelse
        </div>
    </div>

    @if ($canViewContact)
        <div class="space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.profile.contact_methods_title') }}</p>

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
        </div>
    @endif
</div>
