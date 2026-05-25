<div>
    <x-page-header title="Profile">
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
                    <x-button type="button" class="btn-primary" data-profile-unsaved-save>
                        {{ __('Save and switch') }}
                    </x-button>
                </div>
            </div>
        </dialog>

        <dialog id="ui-profile-avatar-crop-modal" class="modal backdrop-blur">
            <div class="modal-box max-w-lg ui-modal-surface">
                <h3 class="text-lg font-semibold">{{ __('Crop your avatar') }}</h3>
                <div class="ui-profile-avatar-crop mt-4 w-full" wire:ignore>
                    <div class="w-full" data-profile-avatar-croppie></div>
                </div>
                <div class="modal-action">
                    <x-button type="button" class="btn-ghost" data-profile-avatar-crop-cancel>{{ __('Cancel') }}</x-button>
                    <x-button type="button" class="btn-primary" data-profile-avatar-crop-apply>{{ __('Use cropped image') }}</x-button>
                </div>
            </div>
            <form method="dialog" class="modal-backdrop">
                <button type="submit" class="sr-only">{{ __('Cancel') }}</button>
            </form>
        </dialog>
    </div>
</div>

@push('scripts')
<script>
(() => {
    let profileTabsAbort;
    function initProfileTabsGuard() {
        profileTabsAbort?.abort();
        profileTabsAbort = new AbortController();
        const signal = profileTabsAbort.signal;

        const shell = document.querySelector('[data-ui="profile-tabs-shell"]');
        const root = shell?.querySelector('[x-data]');
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
            const data = window.Alpine?.$data?.(root);
            if (data && typeof data.selected === 'string' && data.selected !== '') {
                return data.selected;
            }
            return 'identity';
        };

        let pendingTab = null;
        let waitingSaveTab = null;
        const baselineByTab = {};

        function snapshotForm(form) {
            const fields = [...form.querySelectorAll('input, select, textarea')]
                .filter((field) => field.name && field.type !== 'hidden')
                .map((field) => {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        return `${field.name}:${field.checked ? '1' : '0'}`;
                    }

                    return `${field.name}:${field.value ?? ''}`;
                });

            return fields.join('|');
        }

        function formIsDirty(tab) {
            const formSelector = tabFormMap[tab];
            const form = formSelector ? shell.querySelector(formSelector) : null;
            if (!form) {
                return false;
            }

            const current = snapshotForm(form);
            if (!(tab in baselineByTab)) {
                baselineByTab[tab] = current;
            }

            return baselineByTab[tab] !== current;
        }

        function captureBaseline(tab) {
            const formSelector = tabFormMap[tab];
            const form = formSelector ? shell.querySelector(formSelector) : null;
            if (!form) {
                return;
            }

            baselineByTab[tab] = snapshotForm(form);
        }

        function switchToPendingTab() {
            if (!pendingTab) {
                return;
            }
            const livewireRoot = shell.closest('[wire\\:id]');
            const lw = window.Livewire?.find(livewireRoot?.getAttribute('wire:id'));
            if (lw) {
                lw.set('tab', pendingTab);
            }
            pendingTab = null;
        }

        root.addEventListener('click', (event) => {
            const tabButton = event.target.closest('[role="tab"]');
            if (!tabButton) {
                return;
            }
            const targetTab = tabButton.dataset.tabName ?? '';
            const tab = currentTab();
            if (!targetTab || targetTab === tab) {
                return;
            }

            if (!formIsDirty(tab)) {
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
                captureBaseline(tab);
                waitingSaveTab = null;
                switchToPendingTab();
            }, { signal });
        });

        Object.keys(tabFormMap).forEach((tab) => {
            captureBaseline(tab);
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
