@php
    use App\Support\Features\FeatureCatalog;

    $tabs = FeatureCatalog::tabs();
    $everyoneItems = FeatureCatalog::everyoneItems();
    $validTabs = FeatureCatalog::validTabKeys();
    $initialTab = in_array(request()->query('tab'), $validTabs, true)
        ? request()->query('tab')
        : 'players';
@endphp

<x-app-layout :seo="$seo">
    <div
        class="px-4 py-8 sm:px-6 lg:px-8"
        data-ui="features-page"
        x-data="{
            tab: @js($initialTab),
            validTabs: @js($validTabs),
            setTab(name) {
                if (! this.validTabs.includes(name)) {
                    return;
                }
                this.tab = name;
                const url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                window.history.replaceState({}, '', url);
            },
        }"
    >
        <header class="mx-auto max-w-3xl text-center">
            <h1 class="text-3xl font-semibold md:text-4xl">{{ __('ui.features.page_title') }}</h1>
            <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed opacity-75 md:text-base">
                {{ __('ui.features.page_intro') }}
            </p>
        </header>

        <section class="mx-auto mt-10 max-w-5xl" aria-labelledby="features-everyone-heading">
            <h2 id="features-everyone-heading" class="text-center text-sm font-semibold uppercase tracking-wide opacity-60">
                {{ __('ui.features.everyone_heading') }}
            </h2>
            <ul class="mt-4 mb-10 flex flex-wrap justify-center gap-2">
                @foreach ($everyoneItems as $item)
                    <li
                        class="tooltip tooltip-top max-w-xs rounded-full border border-base-300 bg-base-100/80 px-3 py-1.5 text-xs"
                        data-tip="{{ __(FeatureCatalog::langKey($item['body_key'])) }}"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <x-icon :name="$item['icon']" class="size-4 shrink-0 text-primary" />
                            {{ __(FeatureCatalog::langKey($item['title_key'])) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

        <x-ui.hr class="mx-auto my-10 max-w-5xl" icon="o-sparkles" color="neutral" />

        <div class="mt-6 mx-auto max-w-5xl overflow-visible">
            <div
                class="flex flex-wrap justify-center gap-2"
                role="tablist"
                aria-label="{{ __('ui.features.page_title') }}"
            >
                @foreach ($tabs as $tabKey => $tab)
                    <button
                        type="button"
                        class="btn btn-sm"
                        :class="tab === @js($tabKey) ? 'btn-primary' : 'btn-ghost'"
                        role="tab"
                        :aria-selected="tab === @js($tabKey) ? 'true' : 'false'"
                        id="features-tab-{{ $tabKey }}-btn"
                        aria-controls="features-panel-{{ $tabKey }}"
                        data-ui="features-tab-{{ $tabKey }}"
                        x-on:click="setTab(@js($tabKey))"
                    >
                        {{ __(FeatureCatalog::langKey($tab['label_key'])) }}
                    </button>
                @endforeach
            </div>

            <div class="mt-8 overflow-visible">
                @foreach ($tabs as $tabKey => $tab)
                    <div
                        id="features-panel-{{ $tabKey }}"
                        role="tabpanel"
                        aria-labelledby="features-tab-{{ $tabKey }}-btn"
                        x-show="tab === @js($tabKey)"
                        x-cloak
                    >
                        <p class="text-center text-sm leading-relaxed opacity-80">
                            {{ __(FeatureCatalog::langKey($tab['intro_key'])) }}
                        </p>

                        @foreach ($tab['groups'] as $group)
                            <section class="mt-10" aria-labelledby="features-group-{{ $tabKey }}-{{ $group['group_key'] }}">
                                <h3
                                    id="features-group-{{ $tabKey }}-{{ $group['group_key'] }}"
                                    class="text-lg font-semibold"
                                >
                                    {{ __(FeatureCatalog::langKey('group_'.$group['group_key'])) }}
                                </h3>
                                <ul class="mt-4 grid gap-4 sm:grid-cols-2">
                                    @foreach ($group['items'] as $item)
                                        <li
                                            class="rounded-2xl border border-base-300 bg-base-100/80 p-5"
                                            data-ui="feature-item-{{ $item['id'] }}"
                                        >
                                            <div class="flex gap-3">
                                                <x-icon :name="$item['icon']" class="mt-0.5 size-6 shrink-0 text-primary" />
                                                <div class="min-w-0">
                                                    <h4 class="font-medium">
                                                        {{ __(FeatureCatalog::langKey($item['title_key'])) }}
                                                    </h4>
                                                    <p class="mt-1.5 text-sm leading-relaxed opacity-80">
                                                        {{ __(FeatureCatalog::langKey($item['body_key'])) }}
                                                    </p>
                                                </div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <section class="mx-auto mt-16 max-w-3xl rounded-3xl border border-primary/25 bg-base-100/90 p-8 text-center shadow-xl shadow-primary/10 md:p-10">
            <h2 class="text-xl font-semibold md:text-2xl">{{ __('ui.features.cta_heading') }}</h2>
            <p class="mx-auto mt-3 max-w-xl text-sm opacity-75">
                @auth
                    {{ __('ui.features.cta_auth') }}
                @else
                    {{ __('ui.features.cta_guest') }}
                @endauth
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                @auth
                    <x-button :link="route('dashboard')" class="btn-primary" wire:navigate>
                        {{ __('ui.features.cta_dashboard') }}
                    </x-button>
                    <x-button :link="route('activities.create')" class="btn-soft" wire:navigate>
                        {{ __('ui.features.cta_create_activity') }}
                    </x-button>
                @else
                    @if (Route::has('register'))
                        <x-button :link="route('register')" class="btn-primary" wire:navigate>
                            {{ __('ui.features.cta_register') }}
                        </x-button>
                    @endif
                    <x-button :link="route('search.index')" class="btn-soft" wire:navigate>
                        {{ __('ui.features.cta_browse') }}
                    </x-button>
                @endauth
            </div>
        </section>
    </div>
</x-app-layout>
