<section class="mt-8 md:mt-16">
    <div class="text-center mb-8">
        <h2 class="text-2xl font-semibold md:text-3xl">{{ __('ui.welcome.problem_solution_heading') }}</h2>
        <p class="mx-auto mt-2 max-w-2xl text-sm opacity-70">{{ __('ui.welcome.problem_solution_subheading') }}</p>
    </div>

    <x-ui.hr class="my-8" icon="o-light-bulb" color="neutral" />

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-primary/30 bg-base-100/80 p-6 md:p-8">
            <h3 class="text-lg font-semibold text-primary/90">{{ __('ui.welcome.problem_heading') }}</h3>
            <ul class="mt-5 space-y-4">
                @foreach (['problem_1', 'problem_2', 'problem_3'] as $key)
                    <li class="flex gap-3 text-sm opacity-85">
                        <x-icon name="o-x-circle" class="mt-0.5 size-5 shrink-0 text-primary/70" />
                        <span>{{ __('ui.welcome.'.$key) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="rounded-2xl border border-accent/30 bg-base-100/80 p-6 shadow-lg shadow-primary/5 md:p-8">
            <h3 class="text-lg font-semibold text-accent">{{ __('ui.welcome.solution_heading') }}</h3>
            <ul class="mt-5 space-y-4">
                @foreach (['solution_1', 'solution_2', 'solution_3'] as $key)
                    <li class="flex gap-3 text-sm opacity-85">
                        <x-icon name="o-check-circle" class="mt-0.5 size-5 shrink-0 text-accent" />
                        <span>{{ __('ui.welcome.'.$key) }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</section>
