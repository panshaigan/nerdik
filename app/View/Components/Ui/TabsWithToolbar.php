<?php

namespace App\View\Components\Ui;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Mary\View\Components\Tab;
use Mary\View\Components\Tabs;

/**
 * Mary-compatible tab host with an optional right-aligned toolbar on the label row.
 *
 * Keeps the same Alpine contract as {@see Tabs} so {@see Tab}
 * children continue to register into the parent `tabs` array. Use for pages that need actions
 * beside tab labels (e.g. activity show, event show).
 */
class TabsWithToolbar extends Component
{
    public function __construct(
        public ?string $id = null,
        public ?string $selected = null,
        public string $labelClass = 'font-semibold pb-1',
        public string $activeClass = 'border-b-[length:var(--border)] border-b-base-content/50',
        public string $labelDivClass = 'border-b-[length:var(--border)] border-b-base-content/10 flex overflow-x-auto',
        public string $tabsClass = 'relative w-full',
        /** Flex row wrapping scrollable labels + optional toolbar (border, alignment). */
        public string $labelBarClass = 'flex w-full min-w-0 items-end border-b border-base-300',
        /** Wrapper around the {@see $toolbar} slot; only rendered when the slot has content. */
        public string $toolbarWrapperClass = 'flex shrink-0 items-center gap-1 px-2 pb-2 pt-2 sm:px-3',
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.ui.tabs-with-toolbar');
    }
}
