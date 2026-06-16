<div
    @class([$containerClass])
    data-ui="user-badge-contact"
>
    <div
        role="button"
        tabindex="0"
        wire:click.stop="openModal"
        wire:keydown.enter.stop="openModal"
        class="cursor-pointer text-left min-w-0 w-full"
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

    <x-modal
        wire:model="modalOpen"
        :title="$user->badgeDisplayName()"
        box-class="max-w-lg overflow-x-hidden ui-modal-surface"
        class="backdrop-blur"
        separator
        data-ui="user-badge-contact-modal"
    >
        @if ($modalOpen)
            <livewire:activities.user-contact-popover
                :target-user-id="$user->id"
                :key="'user-contact-popover-'.$user->id"
            />
        @endif
    </x-modal>
</div>
