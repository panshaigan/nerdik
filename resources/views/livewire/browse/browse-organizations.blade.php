<div class="py-12">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <form
            id="ui-browse-organizations-form"
            wire:submit.prevent="applySearch"
            class="ui-filter-form ui-filter-form-organizations card border border-base-300 bg-base-100 p-4 shadow-sm"
            data-ui="browse-organizations-form"
        >
            <div class="flex flex-wrap items-end gap-4">
                <x-input
                    id="q"
                    wire:model.defer="q"
                    type="text"
                    :label="__('Search')"
                    :placeholder="__('Organization name or description…')"
                    class="ui-field ui-field-search w-full max-w-md"
                    :omit-error="true"
                    data-ui="browse-organizations-search-input"
                />
                <x-button id="ui-browse-organizations-submit" type="submit" class="btn-primary ui-action ui-action-search" data-ui="browse-organizations-search-submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="applySearch">{{ __('Search') }}</span>
                    <span wire:loading wire:target="applySearch">{{ __('Searching…') }}</span>
                </x-button>
                @if ($this->hasActiveFilters())
                    <x-button id="ui-browse-organizations-clear" type="button" wire:click="clearFilters" class="btn-ghost ui-action ui-action-clear" data-ui="browse-organizations-clear">{{ __('Clear') }}</x-button>
                @endif
            </div>
        </form>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($organizations as $organization)
                <article class="ui-card ui-card-organization card border border-base-300 bg-base-100 shadow-sm" data-ui="organization-card">
                    <div class="card-body p-5" data-ui="organization-card-body">
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
