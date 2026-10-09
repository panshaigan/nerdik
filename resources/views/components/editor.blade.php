@php
    $wireModelName = $attributes->whereStartsWith('wire:model')->first();
    $resolvedUuid = $uuid.$wireModelName;
    $resolvedErrorField = $errorField ?? $wireModelName;
    $resolvedEditorId = $id ?? $resolvedUuid;
@endphp

<div>
    <fieldset class="fieldset py-0">
        @if ($label)
            <legend class="fieldset-legend mb-0.5">
                {{ $label }}

                @if ($attributes->get('required'))
                    <span class="text-error">*</span>
                @endif

                @if ($popover)
                    <x-mary-popover offset="5" position="top-start">
                        <x-slot:trigger class="{{ $popoverTriggerClass }}">
                            <x-mary-icon :name="$popoverIcon" class="w-4 h-4 opacity-40 mb-0.5" />
                        </x-slot:trigger>
                        <x-slot:content class="{{ $popoverContentClass }}">
                            {{ $popover }}
                        </x-slot:content>
                    </x-mary-popover>
                @endif
            </legend>
        @endif

        <div
            wire:ignore
            wire:key="tinymce-host-{{ $resolvedEditorId }}"
            x-data="{
                value: @entangle($attributes->wire('model')),
                uploadUrl: '{{ $uploadUrl }}?disk={{ $disk }}&folder={{ $folder }}&_token={{ csrf_token() }}',
                destroyTinyMce() {
                    const editor = tinymce.get(this.$refs.tinymce?.id);
                    if (editor) {
                        try {
                            editor.remove();
                        } catch (e) {
                            console.warn('TinyMCE remove', e);
                        }
                    }
                },
                mountTinyMce() {
                    const target = this.$refs.tinymce;
                    if (!target || typeof tinymce === 'undefined') {
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
                            console.warn('TinyMCE replace', e);
                        }
                    }

                    tinymce.init({
                        {{ $setup() }},

                        @if ($gplLicense)
                            license_key: 'gpl',
                        @endif

                        target,
                        images_upload_url: this.uploadUrl,
                        readonly: {{ json_encode($attributes->get('readonly') || $attributes->get('disabled')) }},
                        skin: document.documentElement.getAttribute('class') == 'dark' ? 'oxide-dark' : 'oxide',
                        content_css: [
                            document.documentElement.getAttribute('class') == 'dark' ? 'dark' : 'default',
                            @if (isset($config['content_css'])) '{{ $config['content_css'] }}' @endif
                        ],

                        @if ($attributes->get('disabled'))
                            content_style: 'body { opacity: 50% }',
                        @else
                            content_style: 'img { max-width: 100%; height: auto; }',
                        @endif

                        setup: (editor) => {
                            editor.on('keyup', () => this.value = editor.getContent());
                            editor.on('change', () => this.value = editor.getContent());
                            editor.on('undo', () => this.value = editor.getContent());
                            editor.on('redo', () => this.value = editor.getContent());
                            editor.on('init', () => editor.setContent(this.value ?? ''));
                            editor.on('OpenWindow', (e) => tinymce.activeEditor.topLevelWindow = e.dialog);

                            this.$watch('value', (newValue) => {
                                if (newValue !== editor.getContent()) {
                                    editor.resetContent(newValue || '');
                                }
                            });
                        },
                        file_picker_callback: (cb, value, meta) => {
                            const formData = new FormData();
                            const input = document.createElement('input');
                            input.setAttribute('type', 'file');
                            input.click();

                            tinymce.activeEditor.topLevelWindow.block('');

                            input.addEventListener('change', (e) => {
                                formData.append('file', e.target.files[0]);
                                formData.append('_token', '{{ csrf_token() }}');

                                fetch(this.uploadUrl, { method: 'POST', body: formData })
                                    .then((response) => response.json())
                                    .then((data) => cb(data.location))
                                    .catch((err) => console.error(err))
                                    .finally(() => tinymce.activeEditor.topLevelWindow.unblock());
                            });
                        }
                    });
                },
                reconcileAfterMorph() {
                    const target = this.$refs.tinymce;
                    if (!target?.isConnected) {
                        this.destroyTinyMce();
                        return;
                    }

                    const editor = tinymce.get(target.id);
                    if (!editor || !editor.getContainer()?.isConnected) {
                        this.mountTinyMce();
                    }
                },
                init() {
                    this.mountTinyMce();

                    const runReconcile = () => {
                        this.$nextTick(() => this.reconcileAfterMorph());
                    };

                    if (typeof Livewire !== 'undefined' && typeof Livewire.hook === 'function') {
                        Livewire.hook('morphed', runReconcile);
                    }
                },
            }"
            x-init="init()"
            x-on:livewire:navigating.window="destroyTinyMce()"
        >
            <input id="{{ $resolvedEditorId }}" x-ref="tinymce" type="textarea" {{ $attributes->whereDoesntStartWith('wire:model') }} />
        </div>

        @if (! $omitError && $errors->has($resolvedErrorField))
            @foreach ($errors->get($resolvedErrorField) as $message)
                @foreach (Arr::wrap($message) as $line)
                    <div class="{{ $errorClass }}" x-class="text-error">{{ $line }}</div>
                    @break($firstErrorOnly)
                @endforeach
                @break($firstErrorOnly)
            @endforeach
        @endif

        @if ($hint)
            <div class="{{ $hintClass }}" x-classes="fieldset-label">{{ $hint }}</div>
        @endif
    </fieldset>
</div>
