@php
    $groupMeta = [
        'events' => ['icon' => 'o-calendar', 'label' => __('ui.nav.search_group_events')],
        'activities' => ['icon' => 'o-puzzle-piece', 'label' => __('ui.nav.search_group_activities')],
        'organizations' => ['icon' => 'o-building-office-2', 'label' => __('ui.nav.search_group_organizations')],
        'users' => ['icon' => 'o-user', 'label' => __('ui.nav.search_group_users')],
        'places' => ['icon' => 'o-map-pin', 'label' => __('ui.nav.search_group_places')],
        'tags' => ['icon' => 'o-tag', 'label' => __('ui.nav.search_group_tags')],
    ];
@endphp

<div
    class="relative min-w-0 w-full flex-1 overflow-visible"
    data-ui="global-nav-search"
    x-data="{
        activeIndex: -1,
        init() {
            this.positionSuggestions();
            this._onDocumentPointerDown = (event) => this.onOutsideClick(event);
            document.addEventListener('pointerdown', this._onDocumentPointerDown, true);
            if (typeof Livewire !== 'undefined' && typeof Livewire.hook === 'function') {
                Livewire.hook('commit', ({ succeed }) => {
                    succeed(() => {
                        this.positionSuggestions();
                        this.activeIndex = -1;
                    });
                });
            }
            window.addEventListener('resize', () => this.positionSuggestions());
            window.addEventListener('scroll', () => this.positionSuggestions(), true);
        },
        destroy() {
            document.removeEventListener('pointerdown', this._onDocumentPointerDown, true);
        },
        suggestionItems() {
            return Array.from(this.$refs.suggestions?.querySelectorAll('[data-flat-index]') ?? []);
        },
        navigateDown() {
            const items = this.suggestionItems();
            if (items.length === 0) {
                return;
            }
            this.activeIndex = this.activeIndex < items.length - 1 ? this.activeIndex + 1 : 0;
            this.applyActiveStyles();
        },
        navigateUp() {
            const items = this.suggestionItems();
            if (items.length === 0) {
                return;
            }
            this.activeIndex = this.activeIndex <= 0 ? items.length - 1 : this.activeIndex - 1;
            this.applyActiveStyles();
        },
        onEnter(event) {
            const items = this.suggestionItems();
            if (items.length === 0 || this.activeIndex < 0) {
                return;
            }
            event.preventDefault();
            items[this.activeIndex]?.click();
        },
        applyActiveStyles() {
            this.$nextTick(() => {
                const panel = this.$refs.suggestions;
                const items = panel?.querySelectorAll('[data-flat-index]') ?? [];
                items.forEach((element) => {
                    const idx = Number(element.dataset.flatIndex);
                    const active = idx === this.activeIndex;
                    element.classList.toggle('bg-base-200', active);
                    element.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                const activeItem = Array.from(items).find(
                    (element) => Number(element.dataset.flatIndex) === this.activeIndex,
                );
                if (panel && activeItem) {
                    this.scrollOptionIntoPanel(activeItem, panel);
                }
            });
        },
        scrollOptionIntoPanel(option, panel) {
            const panelRect = panel.getBoundingClientRect();
            const optionRect = option.getBoundingClientRect();
            if (optionRect.top < panelRect.top) {
                panel.scrollTop -= panelRect.top - optionRect.top;
            } else if (optionRect.bottom > panelRect.bottom) {
                panel.scrollTop += optionRect.bottom - panelRect.bottom;
            }
        },
        activeDescendantId() {
            if (this.activeIndex < 0) {
                return '';
            }
            return 'global-nav-search-option-' + this.activeIndex;
        },
        resetKeyboard() {
            this.activeIndex = -1;
        },
        onOutsideClick(event) {
            const panel = this.$refs.suggestions;
            const field = this.$refs.searchField;
            if (panel?.contains(event.target) || field?.contains(event.target)) {
                return;
            }
            if (! panel) {
                return;
            }
            this.resetKeyboard();
            this.$wire.dismissSuggestions();
        },
        onEscape(event) {
            if (this.$refs.suggestions) {
                event.stopPropagation();
                this.resetKeyboard();
                this.$wire.dismissSuggestions();
                return;
            }
            this.$dispatch('global-nav-search-close-mobile');
        },
        positionSuggestions() {
            this.$nextTick(() => {
                const field = this.$refs.searchField;
                const panel = this.$refs.suggestions;
                if (! field || ! panel) {
                    return;
                }
                const rect = field.getBoundingClientRect();
                const gap = 4;
                const viewportPad = 8;
                const isDesktopNav = window.matchMedia('(min-width: 60rem)').matches;
                let left = rect.left;
                let width = isDesktopNav ? rect.width * 2 : rect.width;
                const maxRight = window.innerWidth - viewportPad;
                if (left + width > maxRight) {
                    width = Math.max(0, maxRight - left);
                }
                if (left < viewportPad) {
                    const overflow = viewportPad - left;
                    left = viewportPad;
                    width = Math.max(0, width - overflow);
                }
                if (isDesktopNav && width < rect.width * 2 && left > viewportPad) {
                    const desiredWidth = rect.width * 2;
                    const shift = Math.min(left - viewportPad, desiredWidth - width);
                    left -= shift;
                    width += shift;
                    if (left + width > maxRight) {
                        width = Math.max(0, maxRight - left);
                    }
                }
                panel.style.position = 'fixed';
                panel.style.top = `${rect.bottom + gap}px`;
                panel.style.left = `${left}px`;
                panel.style.width = `${width}px`;
                panel.style.right = 'auto';
                panel.style.boxSizing = 'border-box';
            });
        },
    }"
    @focusin="positionSuggestions(); $wire.reopenSuggestionsIfEligible()"
    @keydown.arrow-down.prevent="navigateDown()"
    @keydown.arrow-up.prevent="navigateUp()"
    @keydown.enter="onEnter($event)"
    @keydown.escape.stop="onEscape($event)"
>
    <form wire:submit="submitSearch" class="relative min-w-0 flex-1">
        <label
            x-ref="searchField"
            class="input input-bordered input-sm flex w-full min-w-0 items-center gap-2 bg-base-100/90 light:bg-base-100"
        >
            <span class="inline-flex shrink-0 nav:hidden" aria-hidden="true">
                <x-icon name="o-magnifying-glass" class="h-4 w-4 opacity-60" />
            </span>
            <input
                type="text"
                wire:model.live.debounce.300ms="q"
                x-ref="globalSearchInput"
                class="min-w-0 grow bg-transparent text-sm outline-none"
                placeholder="{{ __('ui.nav.search_everything_placeholder') }}"
                autocomplete="off"
                role="combobox"
                aria-autocomplete="list"
                aria-controls="global-nav-search-suggestions"
                @if ($showSuggestions)
                    aria-expanded="true"
                @else
                    aria-expanded="false"
                @endif
                x-bind:aria-activedescendant="activeDescendantId()"
            />
        </label>

        @if ($showSuggestions)
            @teleport('body')
            <div
                x-ref="suggestions"
                id="global-nav-search-suggestions"
                class="z-[200] max-h-[min(24rem,70vh)] overflow-y-auto overflow-x-hidden rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg light:border-neutral"
                role="listbox"
                data-ui="global-nav-search-suggestions"
            >
                <div
                    wire:loading
                    wire:target="q"
                    class="flex items-center gap-2 px-3 py-2 text-sm text-base-content/60"
                    role="status"
                >
                    <span class="loading loading-spinner loading-xs text-primary" aria-hidden="true"></span>
                    {{ __('ui.common.loading') }}
                </div>

                @php $flatIndex = 0; @endphp
                @foreach ($groupMeta as $groupKey => $meta)
                    @if (($suggestions[$groupKey] ?? []) === [])
                        @continue
                    @endif
                    <p class="px-3 pb-1 pt-2 text-xs font-semibold uppercase tracking-wide text-base-content/50">
                        {{ $meta['label'] }}
                    </p>
                    @foreach ($suggestions[$groupKey] as $item)
                        @php
                            $optionId = 'global-nav-search-option-'.$flatIndex;
                        @endphp
                        @if (filled($item['url'] ?? null))
                            <a
                                href="{{ $item['url'] }}"
                                wire:navigate
                                wire:key="global-nav-search-{{ $groupKey }}-{{ $item['id'] }}"
                                id="{{ $optionId }}"
                                data-flat-index="{{ $flatIndex }}"
                                role="option"
                                class="flex items-center gap-2 px-3 py-2 text-sm text-base-content hover:bg-base-200"
                                @click="resetKeyboard(); $dispatch('global-nav-search-close-mobile')"
                            >
                                <x-icon :name="$meta['icon']" class="h-4 w-4 shrink-0 opacity-60" />
                                <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                            </a>
                        @elseif ($item['kind'] === 'organization')
                            <button
                                type="button"
                                wire:key="global-nav-search-org-{{ $item['id'] }}"
                                wire:click="openOrganizationPreview({{ (int) $item['id'] }})"
                                id="{{ $optionId }}"
                                data-flat-index="{{ $flatIndex }}"
                                role="option"
                                class="flex w-full items-center gap-2 px-3 py-2 text-start text-sm text-base-content hover:bg-base-200"
                                @click="resetKeyboard()"
                            >
                                <x-icon :name="$meta['icon']" class="h-4 w-4 shrink-0 opacity-60" />
                                <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                            </button>
                        @else
                            <button
                                type="button"
                                wire:key="global-nav-search-user-{{ $item['id'] }}"
                                wire:click="openUserPreview({{ (int) $item['id'] }})"
                                id="{{ $optionId }}"
                                data-flat-index="{{ $flatIndex }}"
                                role="option"
                                class="flex w-full items-center gap-2 px-3 py-2 text-start text-sm text-base-content hover:bg-base-200"
                                @click="resetKeyboard()"
                            >
                                <x-icon :name="$meta['icon']" class="h-4 w-4 shrink-0 opacity-60" />
                                <span class="min-w-0 truncate">{{ $item['label'] }}</span>
                            </button>
                        @endif
                        @php $flatIndex++; @endphp
                    @endforeach
                @endforeach
            </div>
            @endteleport
        @endif
    </form>

    @if ($organizationPreviewModalOpen && $previewOrganizationId !== null)
        @teleport('body')
            <x-modal
                wire:model="organizationPreviewModalOpen"
                :title="__('ui.common.organization')"
                box-class="overflow-x-hidden ui-modal-surface ui-overlay-shell ui-overlay-sheet"
                class="backdrop-blur modal-bottom md:modal-end"
                separator
                data-ui="overlay-sheet"
            >
                <livewire:activities.organization-contact-popover
                    :target-organization-id="$previewOrganizationId"
                    :key="'global-nav-organization-contact-popover-'.$previewOrganizationId"
                />
            </x-modal>
        @endteleport
    @endif

    @if ($userPreviewModalOpen && $previewUserId !== null)
        @teleport('body')
            <x-modal
                wire:model="userPreviewModalOpen"
                :title="__('ui.common.user')"
                box-class="overflow-x-hidden ui-modal-surface ui-overlay-shell ui-overlay-sheet"
                class="backdrop-blur modal-bottom md:modal-end"
                separator
                data-ui="overlay-sheet"
            >
                <livewire:activities.user-contact-popover
                    :target-user-id="$previewUserId"
                    :key="'global-nav-user-contact-popover-'.$previewUserId"
                />
            </x-modal>
        @endteleport
    @endif
</div>
