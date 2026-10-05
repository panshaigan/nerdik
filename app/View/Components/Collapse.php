<?php

declare(strict_types=1);

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Mary\View\Components\Collapse as MaryCollapse;

/**
 * Mary collapse with an associated label so assistive tech can name the checkbox control.
 */
class Collapse extends MaryCollapse
{
    #[\Override]
    public function render(): View|Closure|string
    {
        return <<<'HTML'
                @aware(['noJoin' => null])

                <div
                    {{
                        $attributes->class([
                            'collapse border-[length:var(--border)] border-base-content/10',
                            'join-item' => !$noJoin,
                            'collapse-arrow' => !$collapsePlusMinus && !$noIcon,
                            'collapse-plus' => $collapsePlusMinus && !$noIcon,
                        ])->except(['open'])
                    }}

                    wire:key="collapse-{{ $uuid }}"
                >
                        @if(isset($noJoin))
                            <input id="radio-{{ $uuid }}" type="radio" value="{{ $name }}" x-model="model" />
                        @else
                            <input id="checkbox-{{ $uuid }}" {{ $attributes->wire('model') }} @if($open) checked @endif type="checkbox" />
                        @endif

                        <label
                            {{ $heading->attributes->merge(['class' => 'collapse-title font-semibold cursor-pointer']) }}
                            for="{{ isset($noJoin) ? 'radio-'.$uuid : 'checkbox-'.$uuid }}"

                            @if(isset($noJoin))
                                :class="model == '{{ $name }}' && 'z-10'"
                                @click="if (model == '{{ $name }}') model = null"
                            @endif
                        >
                            {{ $heading }}
                        </label>
                        <div {{ $content->attributes->merge(["class" => "collapse-content text-sm"]) }} wire:key="content-{{ $uuid }}">
                            @if($separator)
                                <hr class="mb-3 border-t-[length:var(--border)] border-base-content/10" />

                                @if($progressIndicator)
                                    <div class="h-0.5 -mt-6.5 mb-6.5">
                                        <progress
                                            class="progress progress-primary w-full h-0.5"
                                            wire:loading

                                            @if($progressTarget())
                                                wire:target="{{ $progressTarget() }}"
                                             @endif></progress>
                                    </div>
                                @endif
                            @endif

                            {{ $content }}
                        </div>
                </div>
            HTML;
    }
}
