@php
    $id = $field->getId();
    $statePath = $field->getStatePath();
    $isDisabled = $field->isDisabled();
@endphp

<x-dynamic-component
    :component="$field->getFieldWrapperView()"
    :field="$field"
>
    <div
        wire:ignore
        wire:key="filament-tinymce-{{ $id }}"
        x-data="{
            value: $wire.$entangle(@js($statePath)),
            initEditor() {
                if (typeof tinymce === 'undefined') {
                    return;
                }

                const target = this.$refs.editor;
                if (! target) {
                    return;
                }

                const existing = tinymce.get(target.id);
                if (existing?.getContainer?.()?.isConnected) {
                    return;
                }

                if (existing) {
                    try {
                        existing.remove();
                    } catch (e) {
                        console.warn('TinyMCE remove', e);
                    }
                }

                tinymce.init({
                    target,
                    license_key: 'gpl',
                    height: 360,
                    menubar: false,
                    statusbar: false,
                    plugins: 'autolink lists link',
                    toolbar: 'undo redo | blocks | bold italic underline strikethrough | bullist numlist | link removeformat',
                    toolbar_mode: 'wrap',
                    readonly: @js($isDisabled),
                    skin: document.documentElement.classList.contains('dark') ? 'oxide-dark' : 'oxide',
                    content_css: document.documentElement.classList.contains('dark') ? 'dark' : 'default',
                    setup: (editor) => {
                        editor.on('change keyup undo redo', () => {
                            this.value = editor.getContent();
                        });
                        editor.on('init', () => {
                            editor.setContent(this.value ?? '');
                        });
                        this.$watch('value', (newValue) => {
                            if (newValue !== editor.getContent()) {
                                editor.setContent(newValue || '');
                            }
                        });
                    },
                });
            },
            destroyEditor() {
                const editor = tinymce.get(this.$refs.editor?.id);
                if (editor) {
                    try {
                        editor.remove();
                    } catch (e) {
                        console.warn('TinyMCE remove', e);
                    }
                }
            },
        }"
        x-init="
            const loadScript = () => {
                if (typeof tinymce !== 'undefined') {
                    $nextTick(() => initEditor());
                    return;
                }
                if (document.querySelector('script[data-filament-tinymce]')) {
                    const wait = setInterval(() => {
                        if (typeof tinymce !== 'undefined') {
                            clearInterval(wait);
                            $nextTick(() => initEditor());
                        }
                    }, 50);
                    return;
                }
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js';
                script.referrerPolicy = 'origin';
                script.dataset.filamentTinyMce = '1';
                script.onload = () => $nextTick(() => initEditor());
                document.head.appendChild(script);
            };
            loadScript();
        "
        @destroy.window="destroyEditor()"
    >
        <textarea x-ref="editor" id="{{ $id }}" class="hidden"></textarea>
    </div>
</x-dynamic-component>
