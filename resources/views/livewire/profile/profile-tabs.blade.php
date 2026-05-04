<div class="rounded-lg border border-base-300 bg-base-100 p-4 shadow sm:p-8" data-ui="profile-tabs-shell">
    <x-ui.tabs-with-toolbar
        wire:model.live="tab"
        label-div-class="flex gap-5 overflow-x-auto px-3 pt-2"
        label-class="tab tab-lifted tab-md !px-0 !py-2 pb-2 text-sm font-semibold text-base-content/70 hover:text-base-content"
        active-class="!text-base-content border-b border-primary text-primary"
        tabs-class="w-full"
        data-ui="profile-tabs"
    >
        <x-tab name="identity" :label="__('Identity')" class="px-6 py-6" data-ui="profile-tab-identity" icon="o-identification">
            <livewire:profile.update-identity-information-form />
        </x-tab>

        <x-tab name="contact" :label="__('Contact')" class="px-6 py-6" data-ui="profile-tab-contact" icon="o-envelope">
            <livewire:profile.update-contact-information-form />
        </x-tab>

        <x-tab name="avatar" :label="__('Avatar')" class="px-6 py-6" data-ui="profile-tab-avatar" icon="o-user-circle">
            <livewire:profile.update-avatar-form />
        </x-tab>

        <x-tab name="notifications" :label="__('Notifications')" class="px-6 py-6" data-ui="profile-tab-notifications" icon="o-bell">
            <livewire:profile.notification-settings-form />
        </x-tab>

        <x-tab name="advanced" :label="__('Advanced')" class="px-6 py-6" data-ui="profile-tab-advanced" icon="o-cog-6-tooth">
            <div class="space-y-8">
                <livewire:profile.update-password-form />
                <livewire:profile.delete-user-form />
            </div>
        </x-tab>
    </x-ui.tabs-with-toolbar>

    <dialog id="ui-profile-unsaved-dialog" class="modal">
        <div class="modal-box">
            <h3 class="text-lg font-semibold">{{ __('Unsaved changes') }}</h3>
            <p class="mt-2 text-sm text-base-content/80">
                {{ __('You have unsaved changes in this section. Save before switching tabs?') }}
            </p>
            <div class="modal-action">
                <x-button type="button" class="btn-ghost" data-profile-unsaved-cancel>
                    {{ __('Cancel') }}
                </x-button>
                <x-button type="button" class="btn-outline" data-profile-unsaved-discard>
                    {{ __('Discard changes') }}
                </x-button>
                <x-button type="button" class="btn-primary" data-profile-unsaved-save>
                    {{ __('Save and switch') }}
                </x-button>
            </div>
        </div>
    </dialog>
</div>

@push('scripts')
<script>
(() => {
    let profileTabsAbort;
    function initProfileTabsGuard() {
        profileTabsAbort?.abort();
        profileTabsAbort = new AbortController();
        const signal = profileTabsAbort.signal;

        const root = document.querySelector('[data-ui="profile-tabs"]');
        const shell = document.querySelector('[data-ui="profile-tabs-shell"]');
        const dialog = document.querySelector('#ui-profile-unsaved-dialog');
        if (!root || !shell || !dialog) {
            return;
        }

        const tabFormMap = {
            identity: '#ui-profile-identity-form',
            contact: '#ui-profile-contact-form',
            avatar: '#ui-profile-avatar-form',
            notifications: '#ui-profile-notifications-form',
            advanced: '#ui-profile-password-form',
        };
        const saveEventMap = {
            identity: 'profile-identity-updated',
            contact: 'profile-contact-updated',
            avatar: 'profile-avatar-updated',
            notifications: 'profile-notifications-updated',
            advanced: 'password-updated',
        };
        const currentTab = () => {
            const selected = root.querySelector('[role="tab"][aria-selected="true"]');
            const controls = selected?.getAttribute('aria-controls') ?? '';
            return controls.startsWith('tab-') ? controls.replace('tab-', '') : 'identity';
        };

        let pendingTab = null;
        let waitingSaveTab = null;

        function formIsDirty(form) {
            const fields = form.querySelectorAll('input, select, textarea');
            for (const field of fields) {
                if (field.type === 'hidden') {
                    continue;
                }
                if (field.type === 'checkbox' || field.type === 'radio') {
                    if (field.defaultChecked !== field.checked) {
                        return true;
                    }
                    continue;
                }
                if ((field.defaultValue ?? '') !== field.value) {
                    return true;
                }
            }
            return false;
        }

        function switchToPendingTab() {
            if (!pendingTab) {
                return;
            }
            const lw = window.Livewire?.find(root.closest('[wire\\:id]')?.getAttribute('wire:id'));
            if (lw) {
                lw.set('tab', pendingTab);
            }
            pendingTab = null;
        }

        function discardCurrentForm() {
            const tab = currentTab();
            const formSelector = tabFormMap[tab];
            if (!formSelector) {
                return;
            }
            const form = shell.querySelector(formSelector);
            if (!form) {
                return;
            }
            form.reset();
            form.querySelectorAll('input, select, textarea').forEach((field) => {
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        root.addEventListener('click', (event) => {
            const tabButton = event.target.closest('[role="tab"]');
            if (!tabButton) {
                return;
            }
            const targetTab = (tabButton.getAttribute('aria-controls') ?? '').replace('tab-', '');
            const tab = currentTab();
            if (!targetTab || targetTab === tab) {
                return;
            }
            const formSelector = tabFormMap[tab];
            const form = formSelector ? shell.querySelector(formSelector) : null;
            if (!form || !formIsDirty(form)) {
                return;
            }
            pendingTab = targetTab;
            event.preventDefault();
            event.stopImmediatePropagation();
            dialog.showModal();
        }, { capture: true, signal });

        dialog.querySelector('[data-profile-unsaved-cancel]')?.addEventListener('click', () => {
            pendingTab = null;
            dialog.close();
        }, { signal });

        dialog.querySelector('[data-profile-unsaved-discard]')?.addEventListener('click', () => {
            discardCurrentForm();
            dialog.close();
            switchToPendingTab();
        }, { signal });

        dialog.querySelector('[data-profile-unsaved-save]')?.addEventListener('click', () => {
            const tab = currentTab();
            const formSelector = tabFormMap[tab];
            const form = formSelector ? shell.querySelector(formSelector) : null;
            if (!form) {
                dialog.close();
                switchToPendingTab();
                return;
            }
            waitingSaveTab = tab;
            form.requestSubmit();
            dialog.close();
        }, { signal });

        Object.entries(saveEventMap).forEach(([tab, eventName]) => {
            window.addEventListener(eventName, () => {
                if (waitingSaveTab !== tab) {
                    return;
                }
                waitingSaveTab = null;
                switchToPendingTab();
            }, { signal });
        });
    }

    document.addEventListener('livewire:navigating', () => {
        profileTabsAbort?.abort();
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileTabsGuard, { once: true });
    } else {
        initProfileTabsGuard();
    }
    document.addEventListener('livewire:navigated', initProfileTabsGuard);
})();
</script>
@endpush
