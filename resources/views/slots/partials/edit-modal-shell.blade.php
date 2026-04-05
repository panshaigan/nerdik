@auth
    <dialog id="slot-edit-modal" class="modal">
        <div class="modal-box max-w-3xl">
            <div id="slot-edit-modal-body" class="min-h-[4rem]"></div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <x-button type="submit" class="btn-ghost" :aria-label="__('ui.common.cancel')">{{ __('ui.common.cancel') }}</x-button>
        </form>
    </dialog>

    @php
        $slotEditUrlTemplate = url('/slots/__SLOT__/edit');
    @endphp
    <script>
        (function () {
            const modal = document.getElementById('slot-edit-modal');
            const body = document.getElementById('slot-edit-modal-body');
            const template = @json($slotEditUrlTemplate);

            function slotEditUrl(id) {
                return template.replace('__SLOT__', String(id));
            }

            window.openSlotEditModal = async function (slotId) {
                if (!modal || !body) return;
                body.innerHTML = '<div class="flex justify-center py-8"><span class="loading loading-spinner loading-lg"></span></div>';
                modal.showModal();
                try {
                    const res = await fetch(slotEditUrl(slotId), {
                        credentials: 'same-origin',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            Accept: 'text/html',
                        },
                    });
                    if (res.status === 403) {
                        body.innerHTML = '<p class="text-sm text-error">' + @json(__('This action is unauthorized.')) + '</p>';
                        return;
                    }
                    if (!res.ok) {
                        body.innerHTML = '<p class="text-sm text-error">' + @json(__('The given data was invalid.')) + '</p>';
                        return;
                    }
                    const html = await res.text();
                    body.innerHTML = html;
                    if (typeof window.initSlotEditForm === 'function') {
                        window.initSlotEditForm(body);
                    }
                } catch (e) {
                    body.innerHTML = '<p class="text-sm text-error">' + @json(__('The given data was invalid.')) + '</p>';
                }
            };

            @if (session('open_slot_edit'))
                document.addEventListener('DOMContentLoaded', function () {
                    window.openSlotEditModal({{ (int) session('open_slot_edit') }});
                });
            @endif
        })();
    </script>
@endauth
