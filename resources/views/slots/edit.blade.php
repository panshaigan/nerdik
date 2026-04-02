<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit slot') }}: {{ $slot->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow sm:rounded-lg p-6">
                <form method="POST" action="{{ route('slots.update', $slot) }}">
                    @method('PUT')
                    @include('slots.form', ['submitLabel' => __('Update')])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

