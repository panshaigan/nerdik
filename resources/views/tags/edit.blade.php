<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Edit tag') }}: {{ $tag->translations->firstWhere('locale', app()->getLocale())?->slug ?? ($tag->translations->firstWhere('locale', app()->getLocale())?->label ?? '#'.$tag->id) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="rounded-lg border border-base-300 bg-base-100 p-6 shadow-sm">
                <form method="POST" action="{{ route('tags.update', $tag) }}">
                    @method('PUT')
                    @include('tags.form', ['submitLabel' => __('Update')])
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

