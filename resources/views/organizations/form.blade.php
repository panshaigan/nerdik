@csrf

<div class="space-y-4">
    <div>
        <x-input
            label="{{ __('Name') }}"
            placeholder="{{ __('Name') }}"
            name="name"
            type="text"
            value="{{ old('name', $organization->name ?? '') }}"
            error-field="name"
            required
            inline
        />
    </div>

    <div>
        <x-textarea label="{{ __('Description') }}" name="description" error-field="description" rows="3">{{ old('description', $organization->description ?? '') }}</x-textarea>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <x-button :link="route('organizations.index')" class="btn-outline">{{ __('Cancel') }}</x-button>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('Save') }}</x-button>
</div>
