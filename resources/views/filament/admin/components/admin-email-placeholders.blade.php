@php
    use App\Support\AdminEmail\AdminEmailPlaceholderReplacer;
@endphp

<dl class="fi-fo-field-wrp text-sm text-gray-600 dark:text-gray-400 space-y-2">
    @foreach (AdminEmailPlaceholderReplacer::PLACEHOLDERS as $token)
        <div class="grid gap-0.5 sm:grid-cols-[8rem_1fr] sm:gap-3">
            <dt>
                <code class="rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-semibold text-gray-800 dark:bg-white/10 dark:text-gray-200">{{ $token }}</code>
            </dt>
            <dd>{{ __('admin_custom_email.placeholder_tokens.'.trim($token, '{}')) }}</dd>
        </div>
    @endforeach
</dl>
