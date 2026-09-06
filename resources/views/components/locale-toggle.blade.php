@php
    use App\Enums\AppLocale;

    $current = AppLocale::coerce(app()->getLocale());
    $next = $current->other();
    $currentLabel = $current === AppLocale::En
        ? __('ui.common.language_en')
        : __('ui.common.language_pl');
    $nextLabel = $next === AppLocale::En
        ? __('ui.common.language_en')
        : __('ui.common.language_pl');
@endphp

<a
    {{ $attributes->class('btn btn-ghost btn-sm ui-nav-locale is-active font-display border-b-2 text-primary') }}
    wire:navigate
    x-bind:href="localeSwitchUrl('{{ route('locale.switch', ['locale' => $next->value]) }}')"
    aria-label="{{ __('ui.common.switch_language', ['language' => $nextLabel]) }}"
>
    {{ $currentLabel }}
</a>
