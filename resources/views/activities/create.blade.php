<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Create activity') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('activities.store') }}" data-activity-form>
                    @include('activities.form', ['submitLabel' => __('Create')])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

