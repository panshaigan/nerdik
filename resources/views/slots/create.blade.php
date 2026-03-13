<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ request('mode') === 'mass' ? __('Mass create slots') : __('Create slot') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                @if (request('mode') === 'mass')
                    @include('slots.mass-create')
                @else
                    <form method="POST" action="{{ route('slots.store') }}">
                        @include('slots.form', ['submitLabel' => __('Create')])
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

