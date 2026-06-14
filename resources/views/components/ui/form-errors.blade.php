@props([
    'title' => null,
    'description' => null,
    'icon' => 'o-exclamation-triangle',
    'only' => [],
])

@if ($errors->any())
    @php
        $visibleErrors = $only === []
            ? $errors->all()
            : collect($only)->flatMap(fn (string $field): array => $errors->get($field))->all();
    @endphp

    @if ($visibleErrors !== [])
        <div
            {{ $attributes->class(['ui-form-errors-panel']) }}
            role="alert"
            data-ui="form-errors"
        >
            <div class="ui-form-errors-panel__inner">
                <div class="flex gap-3 sm:gap-4">
                    @if ($title && $icon)
                        <div class="ui-form-errors-panel__icon-wrap shrink-0" aria-hidden="true">
                            <x-icon :name="$icon" class="size-5 text-error" />
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        @if ($title)
                            <p class="font-display text-base font-semibold text-base-content sm:text-lg">{{ $title }}</p>
                        @endif
                        @if ($description)
                            <p class="mt-1 text-sm text-base-content/70">{{ $description }}</p>
                        @endif
                        <ul class="mt-3 space-y-1.5 text-sm">
                            @foreach ($visibleErrors as $error)
                                <li class="flex gap-2 text-base-content/85">
                                    <span class="shrink-0 text-error/70" aria-hidden="true">•</span>
                                    <span>{{ $error }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endif
