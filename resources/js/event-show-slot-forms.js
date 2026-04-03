/**
 * Intercept mass slot creation on the event page (POST JSON, dispatch slot-mutations-refresh).
 */
export function initEventShowSlotForms() {
    document.addEventListener(
        'submit',
        async (e) => {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            if (!form.hasAttribute('data-event-show-async-mass')) {
                return;
            }
            e.preventDefault();

            const box = form.querySelector('[data-slot-form-errors]');
            if (box) {
                box.textContent = '';
                box.classList.add('hidden');
            }

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const fd = new FormData(form);

            try {
                const res = await fetch(form.action, {
                    method: 'POST',
                    body: fd,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    credentials: 'same-origin',
                });
                const data = await res.json().catch(() => ({}));

                if (res.status === 422 && data.errors) {
                    const msgs = Object.values(data.errors).flat();
                    if (box) {
                        box.textContent = msgs.join(' ');
                        box.classList.remove('hidden');
                    }

                    return;
                }

                if (!res.ok) {
                    if (box) {
                        box.textContent = data.message || 'Error';
                        box.classList.remove('hidden');
                    }

                    return;
                }

                form.closest('dialog')?.close();
                window.Livewire?.dispatch('slot-mutations-refresh');
            } catch {
                if (box) {
                    box.textContent = 'Network error';
                    box.classList.remove('hidden');
                }
            }
        },
        true
    );
}
