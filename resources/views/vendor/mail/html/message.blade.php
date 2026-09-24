<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}

@php
    $legalContactEmail = config('legal.contact_email');
    $notificationsSettingsHref = route('profile', ['tab' => 'notifications'], true);
@endphp

@if (filled($legalContactEmail))
{{ __('ui.notifications.mail_do_not_reply', ['email' => $legalContactEmail]) }}

@endif
[{{ __('ui.notifications.mail_manage_notification_settings') }}]({{ $notificationsSettingsHref }})
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
