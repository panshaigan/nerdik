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
    class="relative z-[9999]"
    data-ui="share-menu"
    x-data="{
        open: false,
        copyUrl: @js($copyUrl),
        toggle() {
            this.open = ! this.open;
        },
        close() {
            this.open = false;
        },
        copyLink() {
            window.copyToClipboard(this.copyUrl, { message: @js(__('ui.common.copied')) });
            this.close();
        },
        openExternal(url) {
            window.open(url, '_blank', 'noopener,noreferrer');
            this.close();
        },
    }"
    x-on:keydown.escape.window="close()"
    x-on:click.outside="close()"
>
    <button
        type="button"
        class="btn btn-ghost btn-square btn-sm text-base-content/80 hover:text-primary"
        x-on:click="toggle()"
        :aria-expanded="open"
        aria-haspopup="menu"
        :aria-label="@js(__('ui.share.share'))"
        title="{{ __('ui.share.share') }}"
        data-ui="share-menu-trigger"
    >
        <x-icon name="o-share" class="h-5 w-5" />
    </button>

    <ul
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        role="menu"
        class="absolute end-0 z-[9999] flex w-52 flex-col gap-0.5 rounded-box border border-base-300 bg-base-100 p-2 shadow-lg light:border-neutral {{ $openUpward ? 'bottom-full mb-2' : 'top-full mt-2' }}"
        data-ui="share-menu-list"
        style="display: none;"
    >
        <li role="none">
            <button
                type="button"
                role="menuitem"
                class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-base-200"
                data-ui="share-copy"
                x-on:click="copyLink()"
            >
                <x-icon name="o-clipboard-document" class="h-4 w-4 shrink-0" />
                {{ __('ui.share.copy_link') }}
            </button>
        </li>
        @foreach ($externalTargets as $target)
            @php
                $intentUrl = $shareLinks->intentUrl($payload, $target);
            @endphp
            @if ($intentUrl !== null)
                <li role="none">
                    <button
                        type="button"
                        role="menuitem"
                        class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-base-200"
                        data-ui="share-{{ $target->value }}"
                        x-on:click="openExternal(@js($intentUrl))"
                    >
                        <x-icon
                            :name="$platformIcon[$target->value]"
                            class="h-4 w-4 shrink-0"
                        />
                        {{ __('ui.share.platforms.'.$target->value) }}
                    </button>
                </li>
            @endif
        @endforeach
    </ul>
</div>
