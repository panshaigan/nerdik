<div>
    @if (auth()->guest() && auth_recaptcha_enforced())
        @push('scripts')
            {!! auth_recaptcha_api_script() !!}
            <script data-navigate-once>
                function nerdikFeedbackRecaptcha(token) {
                    const root = document.querySelector('[data-ui="feedback-modal-root"]');
                    if (!root || typeof window.Livewire === 'undefined') {
                        return;
                    }

                    const id = root.getAttribute('wire:id');
                    if (id !== null && id !== '') {
                        window.Livewire.find(id).set('gRecaptchaResponse', token);
                    }
                }
            </script>
        @endpush
    @endif

    @push('scripts')
        <script data-navigate-once>
            window.refreshNerdikFeedbackModalTinyMCE = function () {
                if (typeof tinymce === 'undefined') {
                    return;
                }

                if (Array.isArray(tinymce.editors) && tinymce.editors.length > 0) {
                    [...tinymce.editors].forEach((ed) => {
                        const target = ed.targetElm;
                        if (!target || !target.isConnected) {
                            try {
                                ed.remove();
                            } catch (e) {
                                console.warn('TinyMCE orphan cleanup', e);
                            }
                        }
                    });
                }

                if (!tinymce.editors?.length) {
                    return;
                }

                tinymce.editors.forEach((ed) => {
                    const container = ed.getContainer?.();
                    if (!container || !container.closest('dialog[data-feedback-modal]')) {
                        return;
                    }

                    try {
                        ed.fire('ResizeEditor');
                        const rawH = ed.options?.get?.('height');
                        const h = typeof rawH === 'number' ? rawH : 360;
                        if (ed.theme && typeof ed.theme.resizeTo === 'function') {
                            ed.theme.resizeTo('100%', h);
                        }
                        const iframe = ed.iframeElement;
                        if (iframe && (!iframe.style.height || iframe.offsetHeight < 40)) {
                            iframe.style.height = `${h}px`;
                        }
                    } catch (e) {
                        console.warn('TinyMCE feedback modal refresh', e);
                    }
                });
            };

            window.destroyNerdikFeedbackModalTinyMCE = function () {
                if (typeof tinymce === 'undefined' || !Array.isArray(tinymce.editors) || tinymce.editors.length === 0) {
                    return;
                }

                [...tinymce.editors].forEach((ed) => {
                    const container = ed.getContainer?.();
                    if (!container || !container.closest('dialog[data-feedback-modal]')) {
                        return;
                    }

                    try {
                        ed.remove();
                    } catch (e) {
                        console.warn('TinyMCE feedback modal destroy', e);
                    }
                });
            };

            document.addEventListener('close', (e) => {
                const modal = e.target?.closest?.('dialog[data-feedback-modal]');
                if (!modal) {
                    return;
                }

                window.destroyNerdikFeedbackModalTinyMCE?.();
            });
        </script>
    @endpush

    <div data-ui="feedback-modal-root">
        @if ($open)
            <x-modal
                wire:model="open"
                without-trap-focus
                :title="__('feedback.modal.title')"
                box-class="overflow-x-hidden ui-modal-surface ui-overlay-shell ui-overlay-sheet"
                class="backdrop-blur modal-bottom md:modal-end"
                separator
                data-feedback-modal
                data-ui="overlay-sheet"
            >
                <div
                    wire:key="feedback-modal-body-{{ $modalRenderKey }}"
                    x-data
                    x-init="
                        $wire.set('pageUrl', window.location.href);
                        const stop = (e) => {
                            const t = e.target;
                            if (t && (t.closest?.('.tox-tinymce') || t.closest?.('.tox-toolbar'))) {
                                e.stopPropagation();
                            }
                        };
                        document.addEventListener('focusin', stop);
                        document.addEventListener('mousedown', stop);
                        queueMicrotask(() => window.refreshNerdikFeedbackModalTinyMCE?.());
                        setTimeout(() => window.refreshNerdikFeedbackModalTinyMCE?.(), 50);
                        setTimeout(() => window.refreshNerdikFeedbackModalTinyMCE?.(), 200);
                        return () => {
                            document.removeEventListener('focusin', stop);
                            document.removeEventListener('mousedown', stop);
                            window.destroyNerdikFeedbackModalTinyMCE?.();
                        };
                    "
                    class="min-h-0 space-y-4 pt-2"
                    data-ui="feedback-form"
                    id="ui-feedback-form"
                >
                    <x-select
                        wire:model="type"
                        :label="__('feedback.modal.type')"
                        :placeholder="__('feedback.modal.type')"
                        :options="$this->typeOptions()"
                        required
                        inline
                    />

                    <x-input
                        wire:model="subject"
                        :label="__('feedback.modal.subject')"
                        :placeholder="__('feedback.modal.subject')"
                        type="text"
                        maxlength="200"
                        inline
                    />

                    @guest
                        <x-textarea
                            wire:model="body"
                            :label="__('feedback.modal.body')"
                            rows="8"
                            required
                            data-ui="feedback-body"
                        />
                    @else
                        <div wire:key="feedback-editor-{{ $modalRenderKey }}" data-ui="feedback-body">
                            <x-editor
                                :id="'feedback-body-'.$modalRenderKey"
                                :label="__('feedback.modal.body')"
                                wire:model="body"
                                :gpl-license="true"
                                :config="$this->editorConfig()"
                                folder="feedback/editor"
                                :upload-url="route('feedback.editor-upload', absolute: false)"
                                :omit-error="true"
                                required
                            />
                            <x-field-error :messages="$errors->get('body')" class="mt-2" />
                        </div>
                    @endguest

                    @guest
                        <x-input
                            wire:model="email"
                            :label="__('feedback.modal.email')"
                            :hint="__('feedback.modal.email_hint')"
                            type="email"
                            required
                        />
                    @endguest

                    @if (auth()->guest() && auth_recaptcha_enforced())
                        <div wire:ignore class="flex min-h-[78px] flex-col justify-center" data-ui="feedback-recaptcha">
                            <div
                                data-nerdik-recaptcha
                                data-sitekey="{{ config('captcha.sitekey') }}"
                                data-callback="{{ $this->recaptchaDataCallback() }}"
                            ></div>
                        </div>

                        @error('gRecaptchaResponse')
                            <div class="text-sm font-medium text-error" role="alert">{{ $message }}</div>
                        @enderror
                    @endif
                </div>

                <x-slot:actions>
                    <x-button type="button" class="btn-ghost" wire:click="closeModal" wire:loading.attr="disabled">
                        {{ __('feedback.modal.cancel') }}
                    </x-button>
                    <x-button type="button" class="btn-primary" wire:click="submit" wire:loading.attr="disabled">
                        {{ __('feedback.modal.submit') }}
                    </x-button>
                </x-slot:actions>
            </x-modal>
        @endif
    </div>
</div>
