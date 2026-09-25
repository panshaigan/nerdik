@php
    $tabs = [
        'find' => [
            'label' => 'tab_find',
            'intro' => 'tab_find_intro',
            'bullets' => ['tab_find_1', 'tab_find_2', 'tab_find_3', 'tab_find_4', 'tab_find_5'],
        ],
        'host' => [
            'label' => 'tab_host',
            'intro' => 'tab_host_intro',
            'bullets' => ['tab_host_1', 'tab_host_2', 'tab_host_3', 'tab_host_4', 'tab_host_5'],
        ],
        'organize' => [
            'label' => 'tab_organize',
            'intro' => 'tab_organize_intro',
            'bullets' => ['tab_organize_1', 'tab_organize_2', 'tab_organize_3', 'tab_organize_4', 'tab_organize_5'],
        ],
    ];
@endphp

<section class="mt-8 md:mt-16" data-ui="welcome-capability-tabs">
    <div class="mb-8 text-center">
        <h2 class="text-2xl font-semibold md:text-3xl">{{ __('ui.welcome.features_heading') }}</h2>
        <p class="mx-auto mt-2 max-w-2xl text-sm opacity-70">{{ __('ui.welcome.features_subheading') }}</p>
    </div>

    <x-ui.hr class="my-8" icon="o-sparkles" color="neutral" />

    <div
        class="overflow-visible"
        x-data="{ tab: 'find' }"
    >
        <div class="flex flex-wrap justify-center gap-2" role="tablist" aria-label="{{ __('ui.welcome.features_heading') }}">
            @foreach ($tabs as $name => $tab)
                <button
                    type="button"
                    class="btn btn-sm"
                    :class="tab === '{{ $name }}' ? 'btn-primary' : 'btn-ghost'"
                    role="tab"
                    :aria-selected="tab === '{{ $name }}' ? 'true' : 'false'"
                    id="welcome-tab-{{ $name }}-btn"
                    aria-controls="welcome-panel-{{ $name }}"
                    data-ui="welcome-tab-{{ $name }}"
                    x-on:click="tab = '{{ $name }}'"
                >
                    {{ __('ui.welcome.'.$tab['label']) }}
                </button>
            @endforeach
        </div>

        <div class="mt-6 overflow-visible">
            @foreach ($tabs as $name => $tab)
                <div
                    id="welcome-panel-{{ $name }}"
                    role="tabpanel"
                    aria-labelledby="welcome-tab-{{ $name }}-btn"
                    class="rounded-2xl border border-base-300 bg-base-100/80 p-6 md:p-8"
                    x-show="tab === '{{ $name }}'"
                    x-cloak
                >
                    <p class="text-sm leading-relaxed opacity-85">{{ __('ui.welcome.'.$tab['intro']) }}</p>
                    <ul class="mt-5 space-y-3">
                        @foreach ($tab['bullets'] as $bullet)
                            <li class="flex gap-3 text-sm opacity-85">
                                <x-icon name="o-check-circle" class="mt-0.5 size-5 shrink-0 text-primary" />
                                <span>{{ __('ui.welcome.'.$bullet) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</section>
