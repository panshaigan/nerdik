<div
    @class([$containerClass])
    data-ui="organization-badge-contact"
    x-on:livewire:navigating.window="$wire.closeModal()"
    x-on:livewire:navigated.window="$wire.closeModal()"
>
    <div
        role="button"
        tabindex="0"
        wire:click.stop="openModal"
        wire:keydown.enter.stop="openModal"
        class="ui-user-badge-contact-trigger cursor-pointer overflow-visible text-left min-w-0 w-full"
        title="{{ $contactTooltip ?? __('ui.organizations.popover_tooltip') }}"
        data-ui="organization-badge-contact-trigger"
    >
        <x-user-badge
            :user="$user"
            :organization="$organization"
            :size="$size"
            :name-class="$nameClass"
            :subline="$subline"
            :avatar-only="$avatarOnly"
            :track-nav-avatar="$trackNavAvatar"
            :contact-popover="false"
        />
    </div>

    @if ($modalOpen)
        <x-modal
            wire:model="modalOpen"
            :title="$organization->name"
            box-class="max-w-lg overflow-x-hidden ui-modal-surface"
            class="backdrop-blur"
            separator
            data-ui="organization-badge-contact-modal"
        >
            <livewire:activities.organization-contact-popover
                :target-organization-id="$organization->id"
                :key="'organization-contact-popover-'.$organization->id"
            />
        </x-modal>
    @endif
</div>
