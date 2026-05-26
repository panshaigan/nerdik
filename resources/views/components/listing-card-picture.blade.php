@props(['picture', 'class' => null, 'loading' => 'lazy'])

@php
    /** @var \App\Support\Ui\ListingCardPicture $picture */
@endphp

@if ($picture->sources !== null)
    <x-media-picture :sources="$picture->sources" :class="$class" :loading="$loading" />
@elseif ($picture->staticUrl !== null)
    <img
        src="{{ $picture->staticUrl }}"
        alt=""
        @class([$class])
        loading="{{ $loading }}"
        decoding="async"
    />
@endif
