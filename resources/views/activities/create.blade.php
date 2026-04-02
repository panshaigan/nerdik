<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Create activity') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-5xl sm:px-6 lg:px-8">
            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                <form method="POST" action="{{ route('activities.store') }}" data-activity-form>
                    @include('activities.form', ['submitLabel' => __('Create')])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

