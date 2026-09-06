@php
    $steps = [
        ['title' => 'step_1_title', 'description' => 'step_1_description'],
        ['title' => 'step_2_title', 'description' => 'step_2_description'],
        ['title' => 'step_3_title', 'description' => 'step_3_description'],
        ['title' => 'step_4_title', 'description' => 'step_4_description'],
    ];
@endphp

<section class="mt-8 md:mt-16">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-semibold md:text-3xl">{{ __('ui.welcome.how_it_works_heading') }}</h2>
        <p class="mx-auto mt-2 max-w-2xl text-sm opacity-70">{{ __('ui.welcome.how_it_works_subheading') }}</p>
    </div>

    <x-ui.hr class="my-8" icon="o-map" color="neutral"/>

    <ol class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        @foreach ($steps as $index => $step)
            <li class="relative rounded-2xl border border-base-300 bg-base-100/80 p-6">
                <span class="flex size-9 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-content">
                    {{ $index + 1 }}
                </span>
                <h3 class="mt-4 text-lg font-semibold">{{ __('ui.welcome.'.$step['title']) }}</h3>
                <p class="mt-2 text-sm opacity-75">{{ __('ui.welcome.'.$step['description']) }}</p>
            </li>
        @endforeach
    </ol>
</section>
