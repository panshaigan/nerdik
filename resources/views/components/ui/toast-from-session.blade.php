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

        const fireToast = () => {
            if (fired || typeof window.toast !== 'function') {
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
