@props([
    'payload',
    'openUpward' => false,
])

@php
    /** @var \App\Support\Sharing\SharePayload $payload */
    $shareLinks = app(\App\Support\Sharing\ShareLinks::class);
    $copyUrl = $shareLinks->trackedUrl($payload, \App\Support\Sharing\ShareTarget::Copy);
    $instagramUrl = $shareLinks->trackedUrl($payload, \App\Support\Sharing\ShareTarget::Instagram);
    $platformTargets = \App\Support\Sharing\ShareTarget::menuPlatformCases();
    $platformIcon = [
        \App\Support\Sharing\ShareTarget::Facebook->value => 'o-globe-alt',
        \App\Support\Sharing\ShareTarget::WhatsApp->value => 'o-chat-bubble-oval-left-ellipsis',
        \App\Support\Sharing\ShareTarget::Instagram->value => 'o-camera',
        \App\Support\Sharing\ShareTarget::X->value => 'o-hashtag',
        \App\Support\Sharing\ShareTarget::Telegram->value => 'o-paper-airplane',
    ];
@endphp

<div
    data-ui="share-menu"
    x-data="{
        copyUrl: @js($copyUrl),
        instagramUrl: @js($instagramUrl),
        copyLink() {
            window.copyToClipboard(this.copyUrl, { message: @js(__('ui.common.copied')) });
        },
        copyForInstagram() {
            window.copyToClipboard(this.instagramUrl, { message: @js(__('ui.share.instagram_copied')) });
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
            x-on:click.prevent.stop="copyLink()"
        >
            {{ __('ui.share.copy_link') }}
        </x-ui.overflow-menu-item>
        @foreach ($platformTargets as $target)
            @if ($target->usesClipboard())
                <x-ui.overflow-menu-item
                    :icon="$platformIcon[$target->value]"
                    data-ui="share-{{ $target->value }}"
                    x-on:click.prevent.stop="copyForInstagram()"
                >
                    {{ __('ui.share.platforms.'.$target->value) }}
                </x-ui.overflow-menu-item>
            @else
                @php
                    $menuUrl = $shareLinks->menuUrl($payload, $target);
                @endphp
                @if ($menuUrl !== null)
                    <x-ui.overflow-menu-item
                        :icon="$platformIcon[$target->value]"
                        :href="$menuUrl"
                        external
                        data-ui="share-{{ $target->value }}"
                    >
                        {{ __('ui.share.platforms.'.$target->value) }}
                    </x-ui.overflow-menu-item>
                @endif
            @endif
        @endforeach
    </x-ui.overflow-menu>
</div>
