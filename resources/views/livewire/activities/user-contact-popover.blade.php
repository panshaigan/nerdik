<div class="min-w-0 space-y-4 overflow-x-hidden p-4 text-sm" data-ui="user-contact-popover">
    <div class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.profile.contact_stats_title') }}</p>
        <div class="flex items-center justify-between text-base-content/80">
            <span>{{ __('ui.profile.contact_hosted_activities') }}</span>
            <span class="font-semibold text-base-content">{{ $hostedActivitiesCount }}</span>
        </div>
        @forelse ($statsByType as $stat)
            <div class="flex items-center justify-between text-base-content/80">
                <span>{{ $stat['label'] }}</span>
                <span class="font-semibold text-base-content">{{ $stat['count'] }}</span>
            </div>
        @empty
            <p class="text-base-content/60">{{ __('ui.profile.contact_no_played_activities') }}</p>
        @endforelse
    </div>

    @if ($canViewContact)
        <div class="space-y-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-base-content/60">{{ __('ui.profile.contact_methods_title') }}</p>

            @if ($contacts['email'] || $contacts['facebook'] || $contacts['discord'] || $contacts['google'])
                <div class="flex flex-wrap items-center gap-2">
                    @if ($contacts['email'])
                        <a class="btn btn-xs btn-primary" href="mailto:{{ $contacts['email'] }}">{{ __('ui.profile.contact_email_compose') }}</a>
                        <a class="btn btn-xs btn-outline" href="https://mail.google.com/mail/?view=cm&to={{ rawurlencode($contacts['email']) }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_email_gmail') }}</a>
                        <button type="button" class="btn btn-xs btn-ghost" x-on:click="navigator.clipboard?.writeText('{{ e($contacts['email']) }}')">{{ __('ui.profile.contact_email_copy') }}</button>
                    @endif

                    @if ($contacts['facebook'])
                        <a class="btn btn-xs btn-outline" href="{{ $contacts['facebook'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_facebook_message') }}</a>
                    @endif

                    @if ($contacts['discord'])
                        <a class="btn btn-xs btn-outline" href="{{ $contacts['discord'] }}" target="_blank" rel="noopener">{{ __('ui.profile.contact_discord_message') }}</a>
                    @endif

                    @if ($contacts['google'])
                        <a class="btn btn-xs btn-outline" href="mailto:{{ $contacts['google'] }}">{{ __('ui.profile.contact_google_email') }}</a>
                    @endif
                </div>
            @else
                <p class="text-base-content/60">{{ __('ui.profile.contact_methods_empty') }}</p>
            @endif
        </div>
    @endif
</div>
