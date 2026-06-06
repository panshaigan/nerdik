@props(['picture', 'class' => null, 'loading' => 'lazy'])

@php
    /** @var \App\Support\Ui\ListingCardPicture $picture */
@endphp

@if ($picture->sources !== null)
    <x-media-picture :sources="$picture->sources" :class="$class" :loading="$loading" />
@endif
