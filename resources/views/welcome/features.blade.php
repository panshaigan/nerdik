@php
    $features = [
        ['icon' => 'o-magnifying-glass', 'title' => 'feature_discovery_title', 'description' => 'feature_discovery_description'],
        ['icon' => 'o-calendar-days', 'title' => 'feature_programs_title', 'description' => 'feature_programs_description'],
        ['icon' => 'o-puzzle-piece', 'title' => 'feature_hosting_title', 'description' => 'feature_hosting_description'],
        ['icon' => 'o-inbox-arrow-down', 'title' => 'feature_proposals_title', 'description' => 'feature_proposals_description'],
        ['icon' => 'o-user-group', 'title' => 'feature_participation_title', 'description' => 'feature_participation_description'],
    ];
@endphp

<section class="mt-16">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-semibold md:text-3xl">{{ __('ui.welcome.features_heading') }}</h2>
        <p class="mx-auto mt-2 max-w-2xl text-sm opacity-70">{{ __('ui.welcome.features_subheading') }}</p>
    </div>

    <x-ui.hr class="my-8" icon="o-sparkles" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($features as $feature)
            <div class="rounded-2xl border border-base-300 bg-base-100/80 p-6 transition hover:border-primary/30 hover:shadow-lg hover:shadow-primary/5">
                <div class="flex size-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <x-icon :name="$feature['icon']" class="size-5" />
                </div>
                <h3 class="mt-4 text-lg font-semibold">{{ __('ui.welcome.'.$feature['title']) }}</h3>
                <p class="mt-2 text-sm opacity-75">{{ __('ui.welcome.'.$feature['description']) }}</p>
            </div>
        @endforeach
    </div>
</section>
