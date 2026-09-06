@props(['metadata'])

<title>{{ $metadata->title }}</title>
<meta name="description" content="{{ $metadata->description }}">
<link rel="canonical" href="{{ $metadata->canonical }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="{{ \App\Support\Seo\Seo::ogLocale() }}">
<meta property="og:title" content="{{ $metadata->title }}">
<meta property="og:description" content="{{ $metadata->description }}">
<meta property="og:url" content="{{ $metadata->canonical }}">
<meta property="og:type" content="{{ $metadata->type }}">
@if (filled(config('services.facebook.client_id')))
    <meta property="fb:app_id" content="{{ config('services.facebook.client_id') }}">
@endif
@if ($metadata->image)
    <meta property="og:image" content="{{ $metadata->image }}">
    @if ($metadata->imageAlt)
        <meta property="og:image:alt" content="{{ $metadata->imageAlt }}">
    @endif
    @if ($metadata->imageWidth)
        <meta property="og:image:width" content="{{ $metadata->imageWidth }}">
    @endif
    @if ($metadata->imageHeight)
        <meta property="og:image:height" content="{{ $metadata->imageHeight }}">
    @endif
@endif

<meta name="twitter:card" content="{{ $metadata->image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $metadata->title }}">
<meta name="twitter:description" content="{{ $metadata->description }}">
@if ($metadata->image)
    <meta name="twitter:image" content="{{ $metadata->image }}">
@endif
