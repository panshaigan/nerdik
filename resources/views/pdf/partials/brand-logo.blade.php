@php
    /** @var string $path */
    /** @var int|string $height */
    $height = $height ?? 32;
@endphp
<img src="{{ $path }}" alt="" style="height: {{ $height }}px; width: auto;">
