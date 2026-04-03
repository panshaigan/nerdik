<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Browse organizations') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            <form method="GET" class="card border border-base-300 bg-base-100 p-4 shadow-sm">
                <div class="flex flex-wrap items-end gap-4">
                    <x-input
                        id="q"
                        name="q"
                        type="text"
                        value="{{ request('q') }}"
                        :label="__('Search')"
                        :placeholder="__('Organization name or description…')"
                        class="w-full max-w-md"
                        :omit-error="true"
                    />
                    <x-button type="submit" class="btn-primary">{{ __('Search') }}</x-button>
                    @if (request()->has('q'))
                        <x-button :link="route('browse.organizations')" class="btn-ghost">{{ __('Clear') }}</x-button>
                    @endif
                </div>
            </form>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                @forelse ($organizations as $organization)
                    <article class="card border border-base-300 bg-base-100 shadow-sm">
                        <div class="card-body p-5">
                            <h3 class="card-title text-xl leading-tight">{{ $organization->name }}</h3>
                            @if ($organization->creator)
                                <p class="text-sm opacity-70">{{ __('Owner') }}: {{ $organization->creator->nickname ?? $organization->creator->email }}</p>
                            @endif
                            @if ($organization->desc)
                                <p class="text-sm opacity-80">{{ \Illuminate\Support\Str::limit($organization->desc, 160) }}</p>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="col-span-full rounded-xl border border-base-300 bg-base-100 p-6 text-center opacity-80">
                        {{ __('No organizations found.') }}
                    </div>
                @endforelse
            </div>

            @if ($organizations->hasPages())
                <div class="rounded-xl border border-base-300 bg-base-100 p-4">{{ $organizations->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
