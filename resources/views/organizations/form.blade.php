@csrf

<div class="space-y-4">
    <div>
        <x-input
            label="{{ __('ui.common.name') }}"
            placeholder="{{ __('ui.common.name') }}"
            name="name"
            type="text"
            value="{{ old('name', $organization->name ?? '') }}"
            error-field="name"
            required
            inline
        />
    </div>

    <div>
        <x-textarea label="{{ __('ui.organizations.description') }}" name="description" error-field="description" rows="3">{{ old('description', $organization->description ?? '') }}</x-textarea>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <x-button :link="route('organizations.index')" class="btn-outline">{{ __('ui.common.cancel') }}</x-button>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('ui.common.save') }}</x-button>
</div>
