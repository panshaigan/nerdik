<?php

declare(strict_types=1);

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Mary-compatible popover that uses `isOpen` instead of `open` to avoid collisions
 * with native dialog state during Livewire morph.
 */
class Popover extends Component
{
    public string $uuid;

    public function __construct(
        public ?string $id = null,
        public ?string $position = 'bottom',
        public ?string $offset = '10',
        public mixed $trigger = null,
        public mixed $content = null,
    ) {
        $this->uuid = 'mary'.md5(serialize($this)).$id;
    }

    public function render(): View|Closure|string
    {
        return <<<'HTML'
                <div
                    x-cloak
                    x-data="{
                            isOpen: false,
                            timer: null,
                            show() {
                                this.isOpen = true
                                clearTimeout(this.timer)
                            },
                            hide(){
                                this.timer = setTimeout(() => this.isOpen = false, 300)
                            }
                        }"
                >
                  <!-- TRIGGER -->
                  <div
                    x-ref="myTrigger"
                    @mouseover="show()"
                    @mouseout="hide()"
                    {{ $trigger->attributes->class(["w-fit cursor-pointer"]) }}
                  >
                    {{ $trigger }}
                  </div>

                  <!-- CONTENT -->
                  <div
                    x-show="isOpen"
                    x-anchor.{{ $position }}.offset.{{ $offset }}="$refs.myTrigger"
                    x-transition
                    @mouseover="show()"
                    @mouseout="hide()"
                    {{ $content->attributes->class(["z-[1] shadow-xl border-[length:var(--border)] border-base-content/10 w-fit p-3 rounded-md bg-base-100"]) }}
                  >
                    {{ $content }}
                  </div>
                </div>
            HTML;
    }
}
