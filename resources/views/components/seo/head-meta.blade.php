@props(['metadata'])

<title>{{ $metadata->title }}</title>
<meta name="description" content="{{ $metadata->description }}">
<link rel="canonical" href="{{ $metadata->canonical }}">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="32x32">
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

<meta property="og:title" content="{{ $metadata->title }}">
<meta property="og:description" content="{{ $metadata->description }}">
<meta property="og:url" content="{{ $metadata->canonical }}">
<meta property="og:type" content="{{ $metadata->type }}">
@if ($metadata->image)
    <meta property="og:image" content="{{ $metadata->image }}">
@endif

<meta name="twitter:card" content="{{ $metadata->image ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $metadata->title }}">
<meta name="twitter:description" content="{{ $metadata->description }}">
@if ($metadata->image)
    <meta name="twitter:image" content="{{ $metadata->image }}">
@endif
