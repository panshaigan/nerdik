<div class="py-12 p-1">
    <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
        <div class="mb-6 flex justify-end">
            <x-button
                type="button"
                class="btn-primary btn-circle shadow-sm touch-manipulation"
                wire:click="openCreateModal"
                :tooltip="__('ui.groups.create')"
                :aria-label="__('ui.groups.create')"
                data-ui="groups-create-open"
            >
                <x-icon name="o-plus" class="h-6 w-6" />
            </x-button>
        </div>

        <ul class="space-y-3" role="list" data-ui="groups-list">
            @forelse ($groups as $group)
                <li wire:key="group-{{ $group->id }}" class="rounded-lg border border-base-300 bg-base-100 p-4 shadow-sm">
                    <a
                        href="{{ route('groups.show', $group) }}"
                        wire:navigate
                        class="block font-medium text-base-content hover:text-primary"
                        data-ui="groups-list-item"
                    >
                        {{ $group->name }}
                    </a>
                    @if (filled($group->description))
                        <p class="mt-2 text-sm text-base-content/70">{{ $group->description }}</p>
                    @endif
                </li>
            @empty
                <li class="rounded-lg border border-dashed border-base-300 bg-base-100/50 px-4 py-8 text-center text-sm text-base-content/70">
                    {{ __('ui.groups.empty') }}
                </li>
            @endforelse
        </ul>
    </div>

    <x-modal wire:model="createModalOpen" :title="__('ui.groups.create')" data-ui="groups-create-modal">
        <form wire:submit="createGroup" class="space-y-4" data-ui="groups-create-form">
            <x-input wire:model="name" :label="__('ui.groups.name')" required />
            <x-textarea wire:model="description" :label="__('ui.groups.description')" rows="3" />
            <div class="flex justify-end gap-2">
                <x-button type="button" class="btn-ghost" wire:click="closeCreateModal">
                    {{ __('ui.common.cancel') }}
                </x-button>
                <x-button type="submit" class="btn-primary" spinner="createGroup">
                    {{ __('ui.groups.create') }}
                </x-button>
            </div>
        </form>
    </x-modal>
</div>
