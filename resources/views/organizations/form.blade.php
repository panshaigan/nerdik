@csrf

<div class="space-y-4">
    <div>
        <x-input
            label="{{ __('Name') }}"
            name="name"
            type="text"
            value="{{ old('name', $organization->name ?? '') }}"
            error-field="name"
            required
        />
    </div>

    <div>
        <x-textarea label="{{ __('Description (optional)') }}" name="desc" error-field="desc" rows="3">{{ old('desc', $organization->desc ?? '') }}</x-textarea>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <x-button :link="route('organizations.index')" class="btn-outline">{{ __('Cancel') }}</x-button>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('Save') }}</x-button>
</div>
