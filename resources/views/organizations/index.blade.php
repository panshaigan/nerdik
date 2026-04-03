@push('head')
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
@endpush

@push('scripts')
    <script>
        /**
         * TinyMCE is initialized while the dialog may still be hidden, which breaks layout and
         * can leave the edit view empty. Re-run after the modal is visible (create + edit).
         */
        window.refreshNerdikOrgModalTinyMCE = function () {
            if (typeof tinymce === 'undefined' || !tinymce.editors?.length) {
                return;
            }
            tinymce.editors.forEach((ed) => {
                const container = ed.getContainer?.();
                if (!container || !container.closest('dialog.modal')) {
                    return;
                }
                try {
                    ed.fire('ResizeEditor');
                    const rawH = ed.options?.get?.('height');
                    const h = typeof rawH === 'number' ? rawH : 260;
                    if (ed.theme && typeof ed.theme.resizeTo === 'function') {
                        ed.theme.resizeTo('100%', h);
                    }
                    const iframe = ed.iframeElement;
                    if (iframe && (!iframe.style.height || iframe.offsetHeight < 40)) {
                        iframe.style.height = `${h}px`;
                    }
                } catch (e) {
                    console.warn('TinyMCE modal refresh', e);
                }
            });
        };

        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') {
                return;
            }
            const form = e.target.closest?.('form[data-org-modal-form]');
            if (!form) {
                return;
            }
            const t = e.target;
            if (t.closest?.('.tox-tinymce') || t.closest?.('.tox-toolbar')) {
                return;
            }
            if (t.tagName === 'TEXTAREA') {
                return;
            }
            if (t.tagName === 'BUTTON') {
                return;
            }
            if (t.tagName === 'INPUT' && (t.type === 'checkbox' || t.type === 'radio' || t.type === 'submit' || t.type === 'button')) {
                return;
            }
            if (t.tagName === 'INPUT' || t.tagName === 'SELECT') {
                e.preventDefault();
            }
        });
    </script>
@endpush

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-base-content">
            {{ __('Organizations') }}
        </h2>
    </x-slot>

    <livewire:organizations.organization-index />
</x-app-layout>
