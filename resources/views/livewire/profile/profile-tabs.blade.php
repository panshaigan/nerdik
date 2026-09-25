<div>
    <x-page-header :title="__('ui.profile.title')">
    </x-page-header>

    <div class="ui-content-card relative min-w-0 rounded-2xl mb-4 md:mb-6" data-ui="profile-tabs-shell">
        <x-ui.tabs-with-toolbar
            wire:model.live="tab"
            label-div-class="flex gap-5 px-3"
            label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
            active-class="!text-base-content border-b border-primary text-primary"
            tabs-class="w-full"
            data-ui="profile-tabs"
        >
            <x-tab name="identity" :label="$this->tabLabel('identity', __('ui.profile.tab_identity'))" class="px-6 py-6" data-ui="profile-tab-identity" icon="o-identification">
                @if (isset($loadedTabs['identity']))
                    <livewire:profile.update-identity-information-form :key="'profile-identity-'.auth()->id()" />
                @endif
            </x-tab>

            <x-tab name="contact" :label="$this->tabLabel('contact', __('ui.profile.tab_contact'))" class="px-6 py-6" data-ui="profile-tab-contact" icon="o-envelope">
                @if (isset($loadedTabs['contact']))
                    <livewire:profile.update-contact-information-form :key="'profile-contact-'.auth()->id()" />
                @endif
            </x-tab>

            <x-tab name="avatar" :label="$this->tabLabel('avatar', __('ui.profile.tab_avatar'))" class="px-6 py-6" data-ui="profile-tab-avatar" icon="o-user-circle">
                @if (isset($loadedTabs['avatar']))
                    <livewire:profile.update-avatar-form :key="'profile-avatar-'.auth()->id()" />
                @endif
            </x-tab>

            <x-tab name="images" :label="$this->tabLabel('images', __('ui.profile.tab_images'))" class="px-6 py-6" data-ui="profile-tab-images" icon="o-photo">
                @if (isset($loadedTabs['images']))
                    <livewire:profile.manage-gallery-form :key="'profile-images-'.auth()->id()" />
                @endif
            </x-tab>

            <x-tab name="notifications" :label="$this->tabLabel('notifications', __('ui.profile.tab_notifications'))" class="px-6 py-6" data-ui="profile-tab-notifications" icon="o-bell">
                @if (isset($loadedTabs['notifications']))
                    <livewire:profile.notification-settings-form :key="'profile-notifications-'.auth()->id()" />
                @endif
            </x-tab>

            <x-tab name="advanced" :label="$this->tabLabel('advanced', __('ui.profile.tab_advanced'))" class="px-6 py-6" data-ui="profile-tab-advanced" icon="o-cog-6-tooth">
                @if (isset($loadedTabs['advanced']))
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <livewire:profile.update-email-form :key="'profile-email-'.auth()->id()" />
                        <livewire:profile.update-password-form :key="'profile-password-'.auth()->id()" />
                    </div>
                    <div class="space-y-8">
                        <livewire:profile.delete-user-form :key="'profile-delete-'.auth()->id()" />
                    </div>
                @endif
            </x-tab>
        </x-ui.tabs-with-toolbar>

        @if (isset($loadedTabs['avatar']))
            <x-image-crop-modal :title="__('ui.profile.crop_avatar')" />
        @endif
    </div>
</div>

@push('scripts')
<script>
(() => {
    let profileFormValidationScrollHooked = false;
    let profileFormSubmitAt = 0;
    let profileFormSubmitClearTimer = null;

    function profileFormRegisterValidationScrollHook() {
        if (profileFormValidationScrollHooked) {
            return;
        }
        if (typeof window.Livewire === 'undefined' || typeof window.Livewire.hook !== 'function') {
            return;
        }
        profileFormValidationScrollHooked = true;
        document.addEventListener(
            'submit',
            (e) => {
                const form = e.target;
                if (!form?.matches?.('form[data-ui$="-form"]') || !form.closest('[data-ui="profile-tabs-shell"]')) {
                    return;
                }
                profileFormSubmitAt = Date.now();
                clearTimeout(profileFormSubmitClearTimer);
                profileFormSubmitClearTimer = setTimeout(() => {
                    profileFormSubmitAt = 0;
                }, 5000);
            },
            true,
        );
        window.Livewire.hook('morphed', () => {
            if (!profileFormSubmitAt) {
                return;
            }
            requestAnimationFrame(() => {
                if (!profileFormSubmitAt) {
                    return;
                }
                const shell = document.querySelector('[data-ui="profile-tabs-shell"]');
                if (!shell) {
                    return;
                }
                const err =
                    shell.querySelector('ul.text-error')
                    || shell.querySelector('fieldset .text-error')
                    || shell.querySelector('.label.text-error')
                    || shell.querySelector('[class*="input-error"]')
                    || shell.querySelector('.text-error');
                if (err) {
                    err.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    clearTimeout(profileFormSubmitClearTimer);
                    profileFormSubmitAt = 0;
                }
            });
        });
    }

    document.addEventListener('livewire:init', profileFormRegisterValidationScrollHook);
    document.addEventListener('DOMContentLoaded', profileFormRegisterValidationScrollHook);
    window.addEventListener('load', profileFormRegisterValidationScrollHook);
    profileFormRegisterValidationScrollHook();
})();
</script>
@endpush
