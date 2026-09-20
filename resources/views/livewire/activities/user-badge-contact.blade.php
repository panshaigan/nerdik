<div
    @class([$containerClass])
    data-ui="user-badge-contact"
>
    <div
        role="button"
        tabindex="0"
        wire:click.stop="openModal"
        wire:keydown.enter.stop="openModal"
        class="ui-user-badge-contact-trigger inline-flex w-fit max-w-full min-w-0 cursor-pointer overflow-hidden text-left"
        title="{{ $contactTooltip ?? __('ui.common.click_for_details') }}"
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
            class="max-w-full"
        />
    </div>

    @if ($modalOpen)
        @teleport('body')
            <x-modal
                wire:model="modalOpen"
                :title="__('ui.common.user')"
                box-class="max-w-lg overflow-x-hidden ui-modal-surface ui-overlay-shell"
                class="backdrop-blur"
                separator
                data-ui="user-badge-contact-modal"
            >
                <livewire:activities.user-contact-popover
                    :target-user-id="$user->id"
                    :context-activity-id="$contextActivityId"
                    :context-organization-id="$contextOrganizationId"
                    :key="'user-contact-popover-'.$user->id.'-'.($contextActivityId ?? '0').'-'.($contextOrganizationId ?? '0')"
                />
            </x-modal>
        @endteleport
    @endif
</div>
