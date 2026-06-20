@php
    $benefits = [
        ['icon' => 'o-clipboard-document-list', 'title' => 'benefit_organizers_title', 'description' => 'benefit_organizers_description'],
        ['icon' => 'o-bolt', 'title' => 'benefit_players_title', 'description' => 'benefit_players_description'],
        ['icon' => 'o-home-modern', 'title' => 'benefit_community_title', 'description' => 'benefit_community_description'],
    ];
@endphp

<section class="mt-16">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-semibold md:text-3xl">{{ __('ui.welcome.benefits_heading') }}</h2>
        <p class="mx-auto mt-2 max-w-2xl text-sm opacity-70">{{ __('ui.welcome.benefits_subheading') }}</p>
    </div>

    <x-ui.hr class="my-8" icon="o-heart" />

    <div class="grid gap-6 lg:grid-cols-3">
        @foreach ($benefits as $benefit)
            <div class="rounded-2xl border border-base-300 bg-gradient-to-b from-base-100/90 to-base-100/60 p-6 md:p-8">
                <x-icon :name="$benefit['icon']" class="size-8 text-primary" />
                <h3 class="mt-4 text-xl font-semibold">{{ __('ui.welcome.'.$benefit['title']) }}</h3>
                <p class="mt-3 text-sm leading-relaxed opacity-80">{{ __('ui.welcome.'.$benefit['description']) }}</p>
            </div>
        @endforeach
    </div>

    <p class="mx-auto mt-8 max-w-3xl text-center text-sm opacity-70">{{ __('ui.welcome.benefits_contrast') }}</p>
</section>
