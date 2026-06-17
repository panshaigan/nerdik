<div
    @class([$containerClass])
    data-ui="user-badge-contact"
    x-on:livewire:navigating.window="$wire.closeModal()"
    x-on:livewire:navigated.window="$wire.closeModal()"
>
    <div
        role="button"
        tabindex="0"
        wire:click.stop="openModal"
        wire:keydown.enter.stop="openModal"
        class="ui-user-badge-contact-trigger cursor-pointer overflow-visible text-left min-w-0 w-full"
        title="{{ $contactTooltip ?? __('ui.profile.contact_popover_tooltip') }}"
        data-ui="user-badge-contact-trigger"
    >
        <x-user-badge
            :user="$user"
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
            :title="$user->displayName()"
            box-class="max-w-lg overflow-x-hidden ui-modal-surface"
            class="backdrop-blur"
            separator
            data-ui="user-badge-contact-modal"
        >
            <livewire:activities.user-contact-popover
                :target-user-id="$user->id"
                :key="'user-contact-popover-'.$user->id"
            />
        </x-modal>
    @endif
</div>
