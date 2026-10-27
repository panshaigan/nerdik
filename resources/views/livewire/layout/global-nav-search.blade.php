@php
    $groupMeta = [
        'events' => ['icon' => 'o-calendar', 'label' => __('ui.nav.search_group_events')],
        'activities' => ['icon' => 'o-puzzle-piece', 'label' => __('ui.nav.search_group_activities')],
        'organizations' => ['icon' => 'o-building-office-2', 'label' => __('ui.nav.search_group_organizations')],
        'users' => ['icon' => 'o-user', 'label' => __('ui.nav.search_group_users')],
        'places' => ['icon' => 'o-map-pin', 'label' => __('ui.nav.search_group_places')],
        'tags' => ['icon' => 'o-tag', 'label' => __('ui.nav.search_group_tags')],
    ];
    $showSuggestions = mb_strlen(trim($q)) >= 2 && $hasSuggestions;
@endphp

<div
    class="relative min-w-0 flex-1 overflow-visible sm:flex-none"
    data-ui="global-nav-search"
    x-data="{
        activeIndex: -1,
        init() {
            this.positionSuggestions();
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
                const items = this.$refs.suggestions?.querySelectorAll('[data-flat-index]') ?? [];
                items.forEach((element) => {
                    const idx = Number(element.dataset.flatIndex);
                    const active = idx === this.activeIndex;
                    element.classList.toggle('bg-base-200', active);
                    element.setAttribute('aria-selected', active ? 'true' : 'false');
                });
                items.find((element) => Number(element.dataset.flatIndex) === this.activeIndex)
                    ?.scrollIntoView({ block: 'nearest' });
            });
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
            const field = this.$refs.globalSearchInput?.closest('label');
            if (panel?.contains(event.target) || field?.contains(event.target)) {
                return;
            }
            this.resetKeyboard();
        },
        positionSuggestions() {
            this.$nextTick(() => {
                const input = this.$refs.globalSearchInput;
                const panel = this.$refs.suggestions;
                if (! input || ! panel) {
                    return;
                }
                const rect = input.getBoundingClientRect();
                panel.style.position = 'fixed';
                panel.style.top = `${rect.bottom + 4}px`;
                panel.style.left = `${rect.left}px`;
                panel.style.width = `${Math.max(rect.width, 16 * 16)}px`;
                panel.style.right = 'auto';
            });
        },
    }"
    @focusin="positionSuggestions()"
    @click.outside="onOutsideClick($event)"
    @keydown.arrow-down.prevent="navigateDown()"
    @keydown.arrow-up.prevent="navigateUp()"
    @keydown.enter="onEnter($event)"
    @keydown.escape="resetKeyboard(); $dispatch('global-nav-search-close-mobile')"
>
    <form wire:submit="submitSearch" class="relative min-w-0 flex-1">
        <label class="input input-bordered input-sm flex w-full min-w-0 items-center gap-2 bg-base-100/90 light:bg-base-100">
            <x-icon name="o-magnifying-glass" class="h-4 w-4 shrink-0 opacity-60" />
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
