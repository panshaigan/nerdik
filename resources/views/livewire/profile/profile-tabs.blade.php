<div>
    <x-page-header :title="__('ui.profile.title')">
    </x-page-header>

    <x-ui.toast-from-session />

    <div class="ui-content-card rounded-2xl mt-4" data-ui="profile-tabs-shell">
        <x-ui.tabs-with-toolbar
            wire:model.live="tab"
            label-div-class="flex gap-5 px-3"
            label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
            active-class="!text-base-content border-b border-primary text-primary"
            tabs-class="w-full"
            data-ui="profile-tabs"
        >
            <x-tab name="identity" :label="$this->tabLabel('identity', __('ui.profile.tab_identity'))" class="px-6 py-6" data-ui="profile-tab-identity" icon="o-identification">
                <livewire:profile.update-identity-information-form />
            </x-tab>

            <x-tab name="contact" :label="$this->tabLabel('contact', __('ui.profile.tab_contact'))" class="px-6 py-6" data-ui="profile-tab-contact" icon="o-envelope">
                <livewire:profile.update-contact-information-form />
            </x-tab>

            <x-tab name="avatar" :label="$this->tabLabel('avatar', __('ui.profile.tab_avatar'))" class="px-6 py-6" data-ui="profile-tab-avatar" icon="o-user-circle">
                <livewire:profile.update-avatar-form />
            </x-tab>

            <x-tab name="notifications" :label="$this->tabLabel('notifications', __('ui.profile.tab_notifications'))" class="px-6 py-6" data-ui="profile-tab-notifications" icon="o-bell">
                <livewire:profile.notification-settings-form />
            </x-tab>

            <x-tab name="advanced" :label="$this->tabLabel('advanced', __('ui.profile.tab_advanced'))" class="px-6 py-6" data-ui="profile-tab-advanced" icon="o-cog-6-tooth">
                <div class="space-y-8">
                    <livewire:profile.update-email-form />
                    <livewire:profile.update-password-form />
                    <livewire:profile.delete-user-form />
                </div>
            </x-tab>
        </x-ui.tabs-with-toolbar>

        <x-image-crop-modal :title="__('ui.profile.crop_avatar')" />
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
