@props([
    'payload',
    'openUpward' => false,
])

@php
    /** @var \App\Support\Sharing\SharePayload $payload */
    $shareLinks = app(\App\Support\Sharing\ShareLinks::class);
    $copyUrl = $shareLinks->trackedUrl($payload, \App\Support\Sharing\ShareTarget::Copy);
    $externalTargets = \App\Support\Sharing\ShareTarget::externalCases();
    $platformIcon = [
        \App\Support\Sharing\ShareTarget::Facebook->value => 'o-globe-alt',
        \App\Support\Sharing\ShareTarget::WhatsApp->value => 'o-chat-bubble-oval-left-ellipsis',
        \App\Support\Sharing\ShareTarget::X->value => 'o-hashtag',
        \App\Support\Sharing\ShareTarget::Telegram->value => 'o-paper-airplane',
    ];
@endphp

<div
    data-ui="share-menu"
    x-data="{
        copyUrl: @js($copyUrl),
        copyLink() {
            window.copyToClipboard(this.copyUrl, { message: @js(__('ui.common.copied')) });
        },
    }"
>
    <x-ui.overflow-menu
        icon="o-share"
        :label="__('ui.share.share')"
        :open-upward="$openUpward"
        panel-class="w-52"
        data-ui="share-menu-trigger"
        list-data-ui="share-menu-list"
    >
        <x-ui.overflow-menu-item
            icon="o-clipboard-document"
            data-ui="share-copy"
            x-on:click="$parent.copyLink()"
        >
            {{ __('ui.share.copy_link') }}
        </x-ui.overflow-menu-item>
        @foreach ($externalTargets as $target)
            @php
                $intentUrl = $shareLinks->intentUrl($payload, $target);
            @endphp
            @if ($intentUrl !== null)
                <x-ui.overflow-menu-item
                    :icon="$platformIcon[$target->value]"
                    :href="$intentUrl"
                    external
                    data-ui="share-{{ $target->value }}"
                >
                    {{ __('ui.share.platforms.'.$target->value) }}
                </x-ui.overflow-menu-item>
            @endif
        @endforeach
    </x-ui.overflow-menu>
</div>
