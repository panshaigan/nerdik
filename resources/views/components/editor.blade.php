@php
    $wireModelName = $attributes->whereStartsWith('wire:model')->first();
    $resolvedUuid = $uuid.$wireModelName;
    $resolvedErrorField = $errorField ?? $wireModelName;
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
            x-data="{
                value: @entangle($attributes->wire('model')),
                uploadUrl: '{{ $uploadUrl }}?disk={{ $disk }}&folder={{ $folder }}&_token={{ csrf_token() }}'
            }"
            x-init="
                tinymce.init({
                    {{ $setup() }},

                    @if ($gplLicense)
                        license_key: 'gpl',
                    @endif

                    target: $refs.tinymce,
                    images_upload_url: uploadUrl,
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

                    setup: function(editor) {
                        editor.on('keyup', () => value = editor.getContent());
                        editor.on('change', () => value = editor.getContent());
                        editor.on('undo', () => value = editor.getContent());
                        editor.on('redo', () => value = editor.getContent());
                        editor.on('init', () => editor.setContent(value ?? ''));
                        editor.on('OpenWindow', (e) => tinymce.activeEditor.topLevelWindow = e.dialog);

                        $watch('value', function (newValue) {
                            if (newValue !== editor.getContent()) {
                                editor.resetContent(newValue || '');
                            }
                        });
                    },
                    file_picker_callback: function(cb, value, meta) {
                        const formData = new FormData();
                        const input = document.createElement('input');
                        input.setAttribute('type', 'file');
                        input.click();

                        tinymce.activeEditor.topLevelWindow.block('');

                        input.addEventListener('change', (e) => {
                            formData.append('file', e.target.files[0]);
                            formData.append('_token', '{{ csrf_token() }}');

                            fetch(uploadUrl, { method: 'POST', body: formData })
                                .then((response) => response.json())
                                .then((data) => cb(data.location))
                                .catch((err) => console.error(err))
                                .finally(() => tinymce.activeEditor.topLevelWindow.unblock());
                        });
                    }
                });
            "
            x-on:livewire:navigating.window="
                () => {
                    const editor = tinymce.get($refs.tinymce?.id);
                    editor?.destroy();
                }
            "
        >
            <input id="{{ $id ?? $resolvedUuid }}" x-ref="tinymce" type="textarea" {{ $attributes->whereDoesntStartWith('wire:model') }} />
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
