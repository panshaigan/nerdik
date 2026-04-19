{{-- Align with Mary ThemeToggle ($persist mary-theme / mary-class). Legacy `theme` key alone was overwriting Mary after livewire:navigated. --}}
<script>
    (() => {
        const parseMary = (key) => {
            const raw = localStorage.getItem(key);
            if (raw == null) {
                return null;
            }
            try {
                const v = JSON.parse(raw);
                return typeof v === 'string' ? v : null;
            } catch {
                const stripped = raw.replaceAll('"', '');
                return stripped === 'light' || stripped === 'dark' ? stripped : null;
            }
        };

        window.applyTheme = () => {
            const fromMary = parseMary('mary-theme');
            const legacy = localStorage.getItem('theme');
            let saved = fromMary === 'light' || fromMary === 'dark' ? fromMary : null;
            if (saved == null && (legacy === 'light' || legacy === 'dark')) {
                saved = legacy;
            }
            if (saved == null) {
                saved = 'dark';
            }
            document.documentElement.setAttribute('data-theme', saved);

            let cls = parseMary('mary-class');
            if (cls !== 'light' && cls !== 'dark') {
                cls = saved;
            }
            document.documentElement.setAttribute('class', cls);

            localStorage.setItem('theme', saved);
        };

        window.toggleTheme = () => {
            const current = document.documentElement.getAttribute('data-theme') || 'dark';
            const next = current === 'dark' ? 'light' : 'dark';
            const nextClass = next === 'dark' ? 'dark' : 'light';
            localStorage.setItem('theme', next);
            localStorage.setItem('mary-theme', JSON.stringify(next));
            localStorage.setItem('mary-class', JSON.stringify(nextClass));
            window.applyTheme();
        };

        window.applyTheme();
        document.addEventListener('livewire:navigated', window.applyTheme);
    })();
</script>
