<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ request('mode') === 'mass' ? __('ui.slots.mass_create_slots') : __('ui.slots.create_slot') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                @if (request('mode') === 'mass')
                    @include('slots.mass-create', ['lockedEvent' => $lockedEvent ?? null])
                @else
                    <form method="POST" action="{{ route('slots.store') }}">
                        @include('slots.form', ['submitLabel' => __('ui.common.create')])
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

