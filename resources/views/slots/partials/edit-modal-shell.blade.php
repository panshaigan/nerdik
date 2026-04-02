@auth
    <dialog id="slot-edit-modal" class="modal">
        <div class="modal-box max-w-3xl">
            <h3 class="text-lg font-semibold text-base-content" id="slot-edit-modal-title">{{ __('ui.events.edit_slot') }}</h3>
            <div id="slot-edit-modal-body" class="mt-4 min-h-[4rem]"></div>
        </div>
        <form method="dialog" class="modal-backdrop">
            <button type="submit" aria-label="{{ __('ui.common.cancel') }}">{{ __('ui.common.cancel') }}</button>
        </form>
    </dialog>

    @php
        $slotEditUrlTemplate = url('/slots/__SLOT__/edit').'?modal=1';
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
