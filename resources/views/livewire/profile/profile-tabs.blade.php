<div>
    <x-page-header :title="__('ui.profile.title')">
    </x-page-header>

    <div class="ui-content-card rounded-2xl" data-ui="profile-tabs-shell">
        <x-ui.tabs-with-toolbar
            wire:model.live="tab"
            label-div-class="flex gap-5 px-3"
            label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
            active-class="!text-base-content border-b border-primary text-primary"
            tabs-class="w-full"
            data-ui="profile-tabs"
        >
            <x-tab name="identity" :label="__('ui.profile.tab_identity')" class="px-6 py-6" data-ui="profile-tab-identity" icon="o-identification">
                <livewire:profile.update-identity-information-form />
            </x-tab>

            <x-tab name="contact" :label="__('ui.profile.tab_contact')" class="px-6 py-6" data-ui="profile-tab-contact" icon="o-envelope">
                <livewire:profile.update-contact-information-form />
            </x-tab>

            <x-tab name="avatar" :label="__('ui.profile.tab_avatar')" class="px-6 py-6" data-ui="profile-tab-avatar" icon="o-user-circle">
                <livewire:profile.update-avatar-form />
            </x-tab>

            <x-tab name="notifications" :label="__('ui.profile.tab_notifications')" class="px-6 py-6" data-ui="profile-tab-notifications" icon="o-bell">
                <livewire:profile.notification-settings-form />
            </x-tab>

            <x-tab name="advanced" :label="__('ui.profile.tab_advanced')" class="px-6 py-6" data-ui="profile-tab-advanced" icon="o-cog-6-tooth">
                <div class="space-y-8">
                    <livewire:profile.update-password-form />
                    <livewire:profile.delete-user-form />
                </div>
            </x-tab>
        </x-ui.tabs-with-toolbar>

        <x-image-crop-modal :title="__('ui.profile.crop_avatar')" />
    </div>
</div>
