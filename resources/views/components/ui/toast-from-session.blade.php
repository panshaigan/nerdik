@php
    $toast = session('ui.toast');
@endphp

@if (is_array($toast) && filled($toast['title'] ?? null))
    @push('scripts')
    <script>
    (() => {
        const payload = @json($toast);
        const cssByType = {
            success: 'alert-success',
            error: 'alert-error',
            warning: 'alert-warning',
            info: 'alert-info',
        };

        let fired = false;
        let attempts = 0;
        const maxAttempts = 40;

        const canFire = () => typeof window.toast === 'function' && window.Alpine;

        const fireToast = () => {
            if (fired) {
                return;
            }

            if (! canFire()) {
                if (attempts++ < maxAttempts) {
                    window.setTimeout(fireToast, 50);
                }

                return;
            }

            fired = true;

            window.toast({
                toast: {
                    type: payload.type || 'info',
                    title: payload.title,
                    description: payload.description || '',
                    icon: '',
                    css: cssByType[payload.type] || 'alert-info',
                    timeout: 4000,
                    noProgress: false,
                },
            });
        };

        // Mary toaster listens via Alpine (@mary-toast.window). Firing before Alpine
        // initializes drops the event with no retry — wait for alpine:initialized.
        document.addEventListener('alpine:initialized', fireToast, { once: true });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fireToast, { once: true });
        } else {
            fireToast();
        }

        document.addEventListener('livewire:navigated', fireToast);
    })();
    </script>
    @endpush
@endif
