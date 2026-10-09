<?php

declare(strict_types=1);

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Mary-compatible modal that avoids binding Alpine state named `open` on {@see HTMLDialogElement},
 * which collides with the native dialog `open` IDL property during Livewire morph.
 */
class Modal extends Component
{
    public function __construct(
        public ?string $id = '',
        public ?string $title = null,
        public ?string $subtitle = null,
        public ?string $boxClass = null,
        public ?bool $separator = false,
        public ?bool $persistent = false,
        public ?bool $withoutTrapFocus = false,
        public ?string $actions = null,
    ) {}

    public function render(): View|Closure|string
    {
        return <<<'HTML'
                @if($id)
                <dialog
                    {{ $attributes->except('wire:model')->class(["modal"]) }}
                    id="{{ $id }}"
                >
                    <div class="modal-box {{ $boxClass }}">
                        @if(!$persistent)
                            <form method="dialog" tabindex="-1">
                                <x-mary-button class="btn-circle btn-sm btn-ghost absolute end-2 top-2 z-[999]" icon="o-x-mark" type="submit" tabindex="-1" />
                            </form>
                        @endif

                        @if($title)
                            <x-mary-header :title="$title" :subtitle="$subtitle" size="text-xl" :separator="$separator" class="!mb-5" />
                        @endif

                        <div>
                            {{ $slot }}
                        </div>

                        @if($separator && $actions)
                            <hr class="border-t-[length:var(--border)] border-base-content/10 mt-5" />
                        @endif

                        @if($actions)
                            <div class="modal-action">
                                {{ $actions }}
                            </div>
                        @endif
                    </div>

                    @if(!$persistent)
                        <form class="modal-backdrop" method="dialog">
                            <button type="submit">close</button>
                        </form>
                    @endif
                </dialog>
                @else
                <div
                    data-ui="modal-alpine-root"
                    x-data="{ isOpen: @entangle($attributes->wire('model')).live }"
                    x-init="
                        const syncDialog = (value) => {
                            const dialog = $refs.dialog;
                            if (! dialog) {
                                return;
                            }

                            if (value) {
                                if (! dialog.open) {
                                    dialog.showModal?.();
                                }
                            } else if (dialog.open) {
                                dialog.close?.();
                            }
                        };

                        $watch('$data.isOpen', (value) => {
                            if (! value) {
                                $dispatch('close');
                            } else {
                                $dispatch('open');
                            }

                            syncDialog(value);
                        });

                        syncDialog($data.isOpen);
                    "
                    @if(!$persistent)
                        @keydown.escape.window = "$wire.{{ $attributes->wire('model')->value() }} = false"
                    @endif
                >
                    <dialog
                        x-ref="dialog"
                        {{ $attributes->except('wire:model')->class(["modal"]) }}
                        x-bind:class="{'modal-open !animate-none': !!$data.isOpen}"
                        @if(!$withoutTrapFocus)
                            x-trap="!!$data.isOpen"
                            x-bind:inert="!$data.isOpen"
                        @endif
                    >
                        <div class="modal-box {{ $boxClass }}">
                            @if(!$persistent)
                                <form method="dialog" tabindex="-1">
                                    <x-mary-button class="btn-circle btn-sm btn-ghost absolute end-2 top-2 z-[999]" icon="o-x-mark" @click="$wire.{{ $attributes->wire('model')->value() }} = false" tabindex="-1" />
                                </form>
                            @endif

                            @if($title)
                                <x-mary-header :title="$title" :subtitle="$subtitle" size="text-xl" :separator="$separator" class="!mb-5" />
                            @endif

                            <div>
                                {{ $slot }}
                            </div>

                            @if($separator && $actions)
                                <hr class="border-t-[length:var(--border)] border-base-content/10 mt-5" />
                            @endif

                            @if($actions)
                                <div class="modal-action">
                                    {{ $actions }}
                                </div>
                            @endif
                        </div>

                        @if(!$persistent)
                            <form class="modal-backdrop" method="dialog">
                                <button @click="$wire.{{ $attributes->wire('model')->value() }} = false" type="button">close</button>
                            </form>
                        @endif
                    </dialog>
                </div>
                @endif
                HTML;
    }
}
