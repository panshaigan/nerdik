@csrf

@php
    $locale = app()->getLocale();
@endphp

<div class="space-y-4">
    <div>
        <x-input
            label="{{ __('Name') }}"
            name="name"
            type="text"
            value="{{ old('name', $place->name ?? '') }}"
            error-field="name"
            required
        />
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-form-select id="place_country_id" name="country_id" :label="__('Country (optional)')" error-field="country_id">
                <option value="">{{ __('None') }}</option>
                @foreach ($countries as $c)
                    <option value="{{ $c->id }}"
                        @selected((string) old('country_id', $place->country_id ?? '') === (string) $c->id)>
                        {{ $c->name($locale) }}
                    </option>
                @endforeach
            </x-form-select>
        </div>
        <div>
            <x-form-select id="place_city_id" name="city_id" :label="__('City (optional)')" error-field="city_id">
                <option value="">{{ __('None') }}</option>
                @foreach ($cities as $ct)
                    <option value="{{ $ct->id }}" data-country="{{ $ct->country_id }}"
                        @selected((string) old('city_id', $place->city_id ?? '') === (string) $ct->id)>
                        {{ $ct->name($locale) }}
                    </option>
                @endforeach
            </x-form-select>
        </div>
    </div>

    <div>
        <x-form-select id="type" name="type" :label="__('Type')" error-field="type" required>
            @foreach (['country','state','city','venue','room'] as $type)
                <option value="{{ $type }}"
                    @selected(old('type', $place->type ?? '') === $type)>
                    {{ ucfirst($type) }}
                </option>
            @endforeach
        </x-form-select>
    </div>

    <div>
        <x-form-select id="parent_id" name="parent_id" :label="__('Parent place (optional)')" error-field="parent_id">
            <option value="">{{ __('None') }}</option>
            @foreach ($parents as $parent)
                <option value="{{ $parent->id }}"
                    @selected((string) old('parent_id', $place->parent_id ?? '') === (string) $parent->id)>
                    {{ $parent->name }} ({{ $parent->type }})
                </option>
            @endforeach
        </x-form-select>
    </div>

    <div>
        <x-input
            label="{{ __('Links (optional)') }}"
            name="links"
            type="text"
            value="{{ old('links', $place->links ?? '') }}"
            error-field="links"
        />
    </div>

    <div>
        <x-textarea label="{{ __('Description (optional)') }}" name="desc" error-field="desc" rows="3">{{ old('desc', $place->desc ?? '') }}</x-textarea>
    </div>

    <x-checkbox
        id="is_online"
        name="is_online"
        value="1"
        :label="__('Is online place')"
        :checked="(bool) old('is_online', $place->is_online ?? false)"
    />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <x-input
                label="{{ __('Latitude (optional)') }}"
                name="latitude"
                type="number"
                step="0.0000001"
                value="{{ old('latitude', $place->latitude ?? '') }}"
                error-field="latitude"
            />
        </div>

        <div>
            <x-input
                label="{{ __('Longitude (optional)') }}"
                name="longitude"
                type="number"
                step="0.0000001"
                value="{{ old('longitude', $place->longitude ?? '') }}"
                error-field="longitude"
            />
        </div>
    </div>
</div>

<div class="mt-6 flex justify-end gap-3">
    <x-button :link="route('places.index')" class="btn-outline">{{ __('Cancel') }}</x-button>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('Save') }}</x-button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const countryEl = document.getElementById('place_country_id');
        const cityEl = document.getElementById('place_city_id');
        if (!countryEl || !cityEl) return;

        function filterCities() {
            const cid = countryEl.value;
            [...cityEl.options].forEach((opt) => {
                if (!opt.value) {
                    opt.hidden = false;
                    return;
                }
                opt.hidden = Boolean(cid && opt.dataset.country !== cid);
            });
            const sel = cityEl.options[cityEl.selectedIndex];
            if (sel && sel.hidden) {
                cityEl.value = '';
            }
        }

        countryEl.addEventListener('change', () => {
            const sel = cityEl.options[cityEl.selectedIndex];
            if (sel && sel.value && countryEl.value && sel.dataset.country !== countryEl.value) {
                cityEl.value = '';
            }
            filterCities();
        });
        filterCities();
    });
</script>
