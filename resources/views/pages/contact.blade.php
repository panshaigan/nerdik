@php
    $content = legal_replace(trans('legal.contact'));
    $email = legal_placeholders()['email'];
@endphp

<x-app-layout :seo="$seo">
    <article class="mx-auto max-w-3xl px-4 py-8 sm:py-12">
        <div class="rich-text-content">
            <h1>{{ $content['title'] }}</h1>

            <p>{{ $content['intro'] }}</p>

            <h2>{{ $content['email_heading'] }}</h2>
            <p>
                {{ $content['email_body'] }}
                <a href="mailto:{{ $email }}" class="link link-primary">{{ $email }}</a>
            </p>

            <h2>{{ $content['response_heading'] }}</h2>
            <p>{{ $content['response_body'] }}</p>

            <h2>{{ $content['privacy_heading'] }}</h2>
            <p>
                {{ $content['privacy_body'] }}
                <a href="{{ route('privacy') }}" class="link link-primary">{{ __('ui.footer.privacy') }}</a>
            </p>
        </div>
    </article>
</x-app-layout>
