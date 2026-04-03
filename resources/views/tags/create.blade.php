<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Create tag') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow-sm">
                <form method="POST" action="{{ route('tags.store') }}">
                    @include('tags.form', ['submitLabel' => __('Create')])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

