<div class="min-w-0 space-y-5 overflow-x-hidden p-4 text-sm" data-ui="organization-contact-popover">
    @if ($targetOrganization !== null)
        <div class="flex flex-col items-center gap-3 text-center" data-ui="organization-contact-popover-hero">
            <div class="avatar">
                <div class="h-28 w-28 shrink-0 overflow-hidden rounded-full border-2 border-base-300 bg-base-300 shadow-[0_0_24px_color-mix(in_oklch,var(--color-primary)_28%,transparent)]">
                    <img
                        src="{{ $targetOrganization->logoUrl() }}"
                        alt="{{ $targetOrganization->name }}"
                        class="h-full w-full object-cover"
                        loading="lazy"
                    />
                </div>
            </div>
        </div>
    @endif

    <div class="space-y-3">
        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.common.activity_stats_title') }}</p>

        <div class="space-y-2" data-ui="organization-contact-popover-scheduled-stats">
            <p class="text-xs font-medium text-base-content/70">{{ __('ui.organizations.scheduled_section') }}</p>
            @forelse ($scheduledStatsByType as $stat)
                <div class="flex items-center justify-between text-base-content/80">
                    <span>{{ __('ui.organizations.scheduled_type', ['type' => $stat['label']]) }}</span>
                    <span class="font-semibold text-base-content">{{ $stat['count'] }}</span>
                </div>
            @empty
                <p class="text-base-content/60">{{ __('ui.organizations.no_scheduled_activities') }}</p>
            @endforelse
        </div>

        <div class="space-y-2" data-ui="organization-contact-popover-participant-stats">
            <p class="text-xs font-medium text-base-content/70">{{ __('ui.organizations.participants_section') }}</p>
            @forelse ($participantStatsByType as $stat)
                <div class="flex items-center justify-between text-base-content/80">
                    <span>{{ __('ui.organizations.participants_type', ['type' => $stat['label']]) }}</span>
                    <span class="font-semibold text-base-content">{{ $stat['count'] }}</span>
                </div>
            @empty
                <p class="text-base-content/60">{{ __('ui.organizations.no_participants') }}</p>
            @endforelse
        </div>
    </div>

    <div class="space-y-2" data-ui="organization-contact-popover-members">
        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.organizations.members_section') }}</p>
        <div class="space-y-2">
            @forelse ($members as $member)
                <x-user-badge
                    :user="$member"
                    size="sm"
                    name-class="truncate text-sm font-medium text-base-content"
                    class="min-w-0"
                />
            @empty
                <p class="text-base-content/60">{{ __('ui.organizations.no_members') }}</p>
            @endforelse
        </div>
    </div>

    @if ($targetOrganization !== null && filled(rich_text_excerpt($targetOrganization->description)))
        <div class="space-y-2" data-ui="organization-contact-popover-description">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.organizations.description_section') }}</p>
            <div class="rich-text-content text-base-content/80">
                {!! rich_text($targetOrganization->description) !!}
            </div>
        </div>
    @endif

    @if ($targetOrganization !== null)
        <div class="border-t border-base-300 pt-4" data-ui="organization-contact-popover-requests">
            @if (! auth()->user()?->canModifyEntity($targetOrganization) && (int) auth()->id() !== (int) $targetOrganization->created_by && (int) auth()->user()?->organization_id !== (int) $targetOrganization->id)
                <livewire:user-requests.send-user-request
                    type="organization_join_request"
                    subject-type="organization"
                    :subject-id="$targetOrganization->id"
                    :recipient-id="$targetOrganization->created_by"
                    :key="'organization-join-'.$targetOrganization->id"
                />
            @endif
        </div>
    @endif
</div>
