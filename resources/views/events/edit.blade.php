<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Edit event') }}: {{ $event->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow">
                <form method="POST" action="{{ route('events.update', $event) }}" data-event-form>
                    @method('PUT')
                    @include('events.form', ['submitLabel' => __('Update')])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

