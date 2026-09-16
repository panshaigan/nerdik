@props([
    'payload',
])

@php
    /** @var \App\Support\Sharing\SharePayload $payload */
    $shareLinks = app(\App\Support\Sharing\ShareLinks::class);
    $copyUrl = $shareLinks->trackedUrl($payload, \App\Support\Sharing\ShareTarget::Copy);
    $nativeUrl = $shareLinks->trackedUrl($payload, \App\Support\Sharing\ShareTarget::Native);
    $externalTargets = \App\Support\Sharing\ShareTarget::externalCases();
@endphp

<div
    class="dropdown dropdown-end relative"
    data-ui="share-menu"
    x-data="{
        canNativeShare: typeof navigator !== 'undefined' && typeof navigator.share === 'function',
        copyUrl: @js($copyUrl),
        nativeShare() {
            if (! this.canNativeShare) {
                return;
            }

            navigator.share({
                title: @js($payload->title),
                text: @js($payload->text),
                url: @js($nativeUrl),
            }).catch(() => {});
        },
        copyLink() {
            window.copyToClipboard(this.copyUrl, { message: @js(__('ui.common.copied')) });
        },
    }"
>
    <x-button
        type="button"
        tabindex="0"
        role="button"
        class="btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
        :tooltip="__('ui.share.share')"
        :aria-label="__('ui.share.share')"
        data-ui="share-menu-trigger"
        icon="o-share"
    />

    <ul
        tabindex="0"
        class="menu dropdown-content z-[100] mt-2 w-52 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg light:border-neutral"
        data-ui="share-menu-list"
    >
        <li>
            <button
                type="button"
                class="gap-2"
                data-ui="share-copy"
                x-on:click="copyLink()"
            >
                <x-icon name="o-clipboard-document" class="h-4 w-4" />
                {{ __('ui.share.copy_link') }}
            </button>
        </li>
        <li x-show="canNativeShare" x-cloak>
            <button
                type="button"
                class="gap-2"
                data-ui="share-native"
                x-on:click="nativeShare()"
            >
                <x-icon name="o-share" class="h-4 w-4" />
                {{ __('ui.share.native') }}
            </button>
        </li>
        @foreach ($externalTargets as $target)
            @php
                $intentUrl = $shareLinks->intentUrl($payload, $target);
            @endphp
            @if ($intentUrl !== null)
                <li>
                    <a
                        href="{{ $intentUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="gap-2"
                        data-ui="share-{{ $target->value }}"
                    >
                        <x-icon
                            :name="match ($target) {
                                \App\Support\Sharing\ShareTarget::Facebook => 'o-globe-alt',
                                \App\Support\Sharing\ShareTarget::WhatsApp => 'o-chat-bubble-oval-left-ellipsis',
                                \App\Support\Sharing\ShareTarget::X => 'o-hashtag',
                                \App\Support\Sharing\ShareTarget::Telegram => 'o-paper-airplane',
                            }"
                            class="h-4 w-4"
                        />
                        {{ __('ui.share.platforms.'.$target->value) }}
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
</div>
