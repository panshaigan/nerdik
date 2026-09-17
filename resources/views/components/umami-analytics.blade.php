@php
    $websiteId = config('umami.website_id');
    $scriptUrl = config('umami.script_url');
    $hostUrl = config('umami.host_url');
    $domains = config('umami.domains');
    $authTag = auth()->check() ? 'authenticated' : 'guest';
@endphp
@if (filled($websiteId) && filled($scriptUrl))
    <script
        defer
        src="{{ $scriptUrl }}"
        data-website-id="{{ $websiteId }}"
        data-tag="{{ $authTag }}"
        data-do-not-track="true"
        data-exclude-search="true"
        @if (filled($hostUrl)) data-host-url="{{ $hostUrl }}" @endif
        @if (filled($domains)) data-domains="{{ $domains }}" @endif
    ></script>
@endif
