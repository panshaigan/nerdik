document.addEventListener('alpine:init', () => {
    Alpine.data('inviteUserSearch', (initialOptionIds = []) => ({
        open: false,
        activeIndex: -1,
        optionIds: initialOptionIds,

        init() {
            this.$watch('$wire.userOptions', (options) => {
                this.optionIds = options.map((option) => option.id);
                this.activeIndex = -1;
                this.applyActiveStyles();
            });

            this.$watch('$wire.lastSearchTerm', (term) => {
                if (term.length >= 2) {
                    this.open = true;
                }
            });
        },

        navigateDown() {
            if (! this.open || this.optionIds.length === 0) {
                return;
            }

            this.activeIndex = this.activeIndex < this.optionIds.length - 1
                ? this.activeIndex + 1
                : 0;
            this.applyActiveStyles();
        },

        navigateUp() {
            if (! this.open || this.optionIds.length === 0) {
                return;
            }

            this.activeIndex = this.activeIndex <= 0
                ? this.optionIds.length - 1
                : this.activeIndex - 1;
            this.applyActiveStyles();
        },

        onEnter(event) {
            if (! this.open || this.optionIds.length === 0) {
                return;
            }

            event.preventDefault();
            const index = this.activeIndex >= 0 ? this.activeIndex : 0;
            this.$wire.selectUser(this.optionIds[index]);
        },

        applyActiveStyles() {
            this.$nextTick(() => {
                const items = this.$refs.suggestions?.querySelectorAll('[data-invite-option-index]') ?? [];

                items.forEach((element, index) => {
                    const active = index === this.activeIndex;
                    element.classList.toggle('bg-base-200', active);
                    element.setAttribute('aria-selected', active ? 'true' : 'false');
                });

                items[this.activeIndex]?.scrollIntoView({ block: 'nearest' });
            });
        },

        activeDescendantId() {
            if (this.activeIndex < 0) {
                return '';
            }

            return `invite-user-option-${this.activeIndex}`;
        },
    }));
});
