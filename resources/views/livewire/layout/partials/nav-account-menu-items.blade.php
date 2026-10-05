@php
    use App\Support\Browse\BrowseSearchUrl;

    /** @var 'dropdown'|'drawer' $variant */
    $variant = $variant ?? 'dropdown';
    $closeOnClick = $closeOnClick ?? false;

    $drawerLinkClass = fn (bool $active): string => $active ? 'active font-display font-medium' : 'font-display';
@endphp

<li>
    <a
        wire:navigate
        href="{{ route('profile') }}"
        data-ui="nav-account-settings"
        @if ($closeOnClick)
            @click="close()"
        @endif
        @if ($variant === 'drawer')
            class="{{ $drawerLinkClass(request()->routeIs('profile')) }}"
        @endif
    >
        <x-icon name="o-cog-6-tooth" class="h-4 w-4 shrink-0" />
        {{ __('ui.nav.account_settings') }}
    </a>
</li>
<li>
    <button
        type="button"
        class="w-full cursor-pointer text-left"
        data-ui="nav-contact"
        @if ($closeOnClick)
            @click="close()"
        @endif
        x-data="{ loadingFeedback: false }"
        x-bind:disabled="loadingFeedback"
        x-on:click="
            if (loadingFeedback) return;
            loadingFeedback = true;
            Promise.resolve(window.prepareNerdikFeedbackModal?.())
                .catch((error) => console.error('Feedback dependency load failed', error))
                .then(() => $dispatch('open-feedback-modal'))
                .finally(() => loadingFeedback = false);
        "
    >
        <x-icon name="o-chat-bubble-left-right" class="h-4 w-4 shrink-0" />
        {{ __('ui.footer.contact') }}
    </button>
</li>
@if (auth()->user()->canCreateEvents())
    <li>
        <a
            wire:navigate
            href="{{ route('organizations.index') }}"
            @if ($closeOnClick)
                @click="close()"
            @endif
            @if ($variant === 'drawer')
                class="{{ $drawerLinkClass(request()->routeIs('organizations.index')) }}"
            @endif
        >
            <x-icon name="o-building-office-2" class="h-4 w-4 shrink-0" />
            {{ __('ui.nav.my_organizations') }}
        </a>
    </li>
@endif
@if (auth()->user()->canCreateEvents())
    <li>
        <a
            wire:navigate
            href="{{ BrowseSearchUrl::myEvents() }}"
            @if ($closeOnClick)
                @click="close()"
            @endif
            @if ($variant === 'drawer')
                class="{{ $drawerLinkClass(BrowseSearchUrl::isMyEvents(request())) }}"
            @endif
        >
            <x-icon name="o-calendar-days" class="h-4 w-4 shrink-0" />
            {{ __('ui.me.menu_events') }}
        </a>
    </li>
@else
    <li>
        <button
            type="button"
            wire:click="openOrganizerRequestModal"
            class="w-full cursor-pointer text-left"
            data-ui="nav-request-organizer"
            @if ($closeOnClick)
                @click="close()"
            @endif
        >
            <x-icon name="o-hand-raised" class="h-4 w-4 shrink-0" />
            {{ __('ui.user_requests.request_organizer_access') }}
        </button>
    </li>
@endif
<li>
    <a
        wire:navigate
        href="{{ BrowseSearchUrl::myActivities() }}"
        @if ($closeOnClick)
            @click="close()"
        @endif
        @if ($variant === 'drawer')
            class="{{ $drawerLinkClass(BrowseSearchUrl::isMyActivities(request())) }}"
        @endif
    >
        <x-icon name="o-puzzle-piece" class="h-4 w-4 shrink-0" />
        {{ __('ui.me.menu_activities') }}
    </a>
</li>
@if (auth()->user()->canCreateEvents())
    <li>
        <a
            wire:navigate
            href="{{ url_with_return(route('events.create')) }}"
            @if ($closeOnClick)
                @click="close()"
            @endif
            @if ($variant === 'drawer')
                class="{{ $drawerLinkClass(request()->routeIs('events.create')) }}"
            @endif
        >
            <x-icon name="o-plus-circle" class="h-4 w-4 shrink-0" />
            {{ __('ui.nav.create_event') }}
        </a>
    </li>
@endif
<li>
    <a
        wire:navigate
        href="{{ url_with_return(route('activities.create')) }}"
        @if ($closeOnClick)
            @click="close()"
        @endif
        @if ($variant === 'drawer')
            class="{{ $drawerLinkClass(request()->routeIs('activities.create')) }}"
        @endif
    >
        <x-icon name="o-plus" class="h-4 w-4 shrink-0" />
        {{ __('ui.nav.create_activity') }}
    </a>
</li>
@if (auth()->user()->is_admin)
    <li class="menu-title mt-1 border-t border-base-300 pt-2 light:border-neutral">
        <span class="text-xs font-semibold uppercase tracking-wide text-base-content/50">
            {{ __('ui.nav.admin_section') }}
        </span>
    </li>
    @include('livewire.layout.partials.admin-ops-menu-items', ['closeOnClick' => $closeOnClick])
@endif
<li>
    <button
        type="button"
        wire:click="logout"
        @if ($closeOnClick)
            @click="close()"
        @endif
    >
        <x-icon name="o-arrow-right-on-rectangle" class="h-4 w-4 shrink-0" />
        {{ __('ui.nav.log_out') }}
    </button>
</li>
