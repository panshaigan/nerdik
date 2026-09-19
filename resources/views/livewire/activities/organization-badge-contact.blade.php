<div
    @class([$containerClass])
    data-ui="organization-badge-contact"
>
    <div
        role="button"
        tabindex="0"
        wire:click.stop="openModal"
        wire:keydown.enter.stop="openModal"
        class="ui-user-badge-contact-trigger inline-flex w-fit max-w-full cursor-pointer overflow-visible text-left min-w-0"
        title="{{ $contactTooltip ?? __('ui.common.click_for_details') }}"
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
        @teleport('body')
            <x-modal
                wire:model="modalOpen"
                :title="$organization->name"
                box-class="max-w-lg overflow-x-hidden ui-modal-surface ui-overlay-shell"
                class="backdrop-blur"
                separator
                data-ui="organization-badge-contact-modal"
            >
                <livewire:activities.organization-contact-popover
                    :target-organization-id="$organization->id"
                    :key="'organization-contact-popover-'.$organization->id"
                />
            </x-modal>
        @endteleport
    @endif
</div>
