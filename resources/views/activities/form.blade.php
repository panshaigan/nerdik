@csrf

@php
    $activityTypes = \App\Http\Controllers\ActivityController::ACTIVITY_TYPES;
    $currentType = old('type', $activity->type ?? '');
@endphp

<div class="space-y-4">
    <div>
        <x-input
            label="{{ __('ui.activities.name') }}"
            name="name"
            type="text"
            value="{{ old('name', $activity->name ?? '') }}"
            error-field="name"
            required
        />
    </div>

    <div>
        <p class="fieldset-legend mb-0.5 font-medium">{{ __('ui.activities.description') }}</p>
        <input id="activity-desc" type="hidden" name="desc" value="{{ old('desc', $activity->desc ?? '') }}">
        <div class="mt-1 overflow-hidden rounded-lg border border-base-300 bg-base-100 shadow-sm">
            <div data-activity-desc-editor class="min-h-[11rem]"></div>
        </div>
        <x-field-error :messages="$errors->get('desc')" class="mt-2" />
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div>
            <fieldset class="fieldset py-0">
                <legend class="fieldset-legend mb-0.5 font-medium">
                    {{ __('ui.activities.type') }}
                    <span class="text-error">*</span>
                </legend>
                <select id="type" name="type" class="select select-bordered w-full border-base-300 bg-base-100" required>
                    <option value="" disabled @selected($currentType === '')>{{ __('ui.activities.choose_type') }}</option>
                    @foreach ($activityTypes as $type)
                        <option value="{{ $type }}" @selected($currentType === $type)>
                            {{ ucfirst($type) }}
                        </option>
                    @endforeach
                </select>
            </fieldset>
            <x-field-error :messages="$errors->get('type')" class="mt-2" />
        </div>

        <div>
            <x-input
                label="{{ __('ui.activities.min_participants') }}"
                name="min_participants"
                type="number"
                min="1"
                data-activity-numeric
                value="{{ old('min_participants', $activity->min_participants ?? '') }}"
                error-field="min_participants"
            >
                <x-slot:append>
                    <button
                        type="button"
                        class="btn btn-xs min-h-8 px-2"
                        data-activity-clear-one="min_participants"
                        aria-label="{{ __('ui.activities.clear_field') }}"
                    >×</button>
                </x-slot:append>
            </x-input>
        </div>

        <div>
            <x-input
                label="{{ __('ui.activities.max_participants') }}"
                name="max_participants"
                type="number"
                min="1"
                data-activity-numeric
                value="{{ old('max_participants', $activity->max_participants ?? '') }}"
                error-field="max_participants"
            >
                <x-slot:append>
                    <button
                        type="button"
                        class="btn btn-xs min-h-8 px-2"
                        data-activity-clear-one="max_participants"
                        aria-label="{{ __('ui.activities.clear_field') }}"
                    >×</button>
                </x-slot:append>
            </x-input>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div>
            <x-input
                label="{{ __('ui.activities.age_limit') }}"
                name="age_limit"
                type="number"
                min="0"
                data-activity-numeric
                value="{{ old('age_limit', $activity->age_limit ?? '') }}"
                error-field="age_limit"
            >
                <x-slot:append>
                    <button
                        type="button"
                        class="btn btn-xs min-h-8 px-2"
                        data-activity-clear-one="age_limit"
                        aria-label="{{ __('ui.activities.clear_field') }}"
                    >×</button>
                </x-slot:append>
            </x-input>
        </div>

        <div>
            <x-input
                label="{{ __('ui.activities.duration_minutes') }}"
                name="duration_minutes"
                type="number"
                min="0"
                step="5"
                data-activity-numeric
                value="{{ old('duration_minutes', $activity->duration_minutes ?? '') }}"
                error-field="duration_minutes"
            >
                <x-slot:append>
                    <button
                        type="button"
                        class="btn btn-xs min-h-8 px-2"
                        data-activity-clear-one="duration_minutes"
                        aria-label="{{ __('ui.activities.clear_field') }}"
                    >×</button>
                </x-slot:append>
            </x-input>
        </div>

        <div>
            <x-input
                label="{{ __('ui.activities.signoff_deadline_hours') }}"
                name="signoff_deadline_hours"
                type="number"
                min="0"
                data-activity-numeric
                class="w-full"
                value="{{ old('signoff_deadline_hours', $activity->signoff_deadline_hours ?? '') }}"
                error-field="signoff_deadline_hours"
            >
                <x-slot:append>
                    <button
                        type="button"
                        class="btn btn-xs min-h-8 px-2"
                        data-activity-clear-one="signoff_deadline_hours"
                        aria-label="{{ __('ui.activities.clear_field') }}"
                    >×</button>
                </x-slot:append>
            </x-input>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="button" data-activity-clear-numeric class="btn btn-outline btn-sm">
            {{ __('ui.activities.clear_numeric_fields') }}
        </button>
    </div>

    @if (isset($tags))
        <div class="mt-4 border-t border-base-300 pt-4">
            <p class="fieldset-legend font-medium text-base-content">{{ __('ui.activities.tags') }}</p>
            <p class="mb-3 text-xs text-base-content/70">{{ __('ui.activities.tags_help') }}</p>
            @include('tags.partials.selector', [
                'tags' => $tags,
                'selectedIds' => old('tag_ids', $activity->exists ? $activity->tags->pluck('id')->toArray() : []),
            ])
            <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
            <x-field-error :messages="$errors->get('new_tags.*.category')" class="mt-2" />
        </div>
    @endif

    <div class="mt-4 border-t border-base-300 pt-4">
        <p class="fieldset-legend font-medium text-base-content">{{ __('ui.activities.activity_options') }}</p>
        <div class="mt-3 rounded-lg border border-base-300 bg-base-100 p-3">
            <div class="space-y-4">
                <label for="passive_host" class="flex cursor-pointer items-start gap-3">
                    <input id="passive_host" name="passive_host" type="checkbox" value="1"
                           class="checkbox checkbox-sm mt-1 shrink-0"
                           @checked(old('passive_host', $activity->passive_host ?? false)) />
                    <span class="text-sm text-base-content">{{ __('ui.activities.passive_host') }}</span>
                </label>

                <label for="is_restricted" class="flex cursor-pointer items-start gap-3">
                    <input id="is_restricted" name="is_restricted" type="checkbox" value="1"
                           class="checkbox checkbox-sm mt-1 shrink-0"
                           @checked(old('is_restricted', $activity->is_restricted ?? false)) />
                    <span class="text-sm text-base-content">{{ __('ui.activities.restricted') }}</span>
                </label>

                <label for="open_for_observers" class="flex cursor-pointer items-start gap-3">
                    <input id="open_for_observers" name="open_for_observers" type="checkbox" value="1"
                           class="checkbox checkbox-sm mt-1 shrink-0"
                           @checked(old('open_for_observers', $activity->open_for_observers ?? false)) />
                    <span class="text-sm text-base-content">{{ __('ui.activities.open_for_observers') }}</span>
                </label>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const activityForm = document.querySelector('form[data-activity-form]');
        if (activityForm) {
            activityForm.addEventListener('keydown', (e) => {
                if (e.key !== 'Enter') return;
                const t = e.target;
                if (t.closest('.ql-editor') || t.closest('.ql-toolbar')) return;
                if (t.tagName === 'TEXTAREA') return;
                if (t.tagName === 'BUTTON') return;
                if (t.tagName === 'INPUT' && (t.type === 'checkbox' || t.type === 'radio' || t.type === 'submit' || t.type === 'button')) return;
                if (t.hasAttribute('data-ts-input')) return;
                if (t.tagName === 'INPUT' || t.tagName === 'SELECT') {
                    e.preventDefault();
                }
            });
        }

        function parseNum(el) {
            const v = el.value.trim();
            if (v === '') return null;
            const n = parseInt(v, 10);
            return Number.isFinite(n) ? n : null;
        }

        function syncActivityMinMax() {
            const minEl = document.querySelector('input[name="min_participants"]');
            const maxEl = document.querySelector('input[name="max_participants"]');
            if (!minEl || !maxEl) return;

            const minV = parseNum(minEl);
            const maxV = parseNum(maxEl);

            if (minV !== null) {
                maxEl.min = String(minV);
            } else {
                maxEl.setAttribute('min', '1');
            }

            if (maxV !== null) {
                minEl.max = String(maxV);
            } else {
                minEl.removeAttribute('max');
            }

            if (minV !== null && maxV !== null && maxV < minV) {
                maxEl.value = String(minV);
            }
        }

        const minEl = document.querySelector('input[name="min_participants"]');
        const maxEl = document.querySelector('input[name="max_participants"]');
        if (minEl && maxEl) {
            ['input', 'change'].forEach((ev) => {
                minEl.addEventListener(ev, syncActivityMinMax);
                maxEl.addEventListener(ev, syncActivityMinMax);
            });
            syncActivityMinMax();
            activityForm?.addEventListener('submit', () => {
                syncActivityMinMax();
            });
        }

        document.querySelector('[data-activity-clear-numeric]')?.addEventListener('click', () => {
            document.querySelectorAll('[data-activity-numeric]').forEach((el) => {
                el.value = '';
            });
            syncActivityMinMax();
        });

        document.querySelectorAll('[data-activity-clear-one]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const name = btn.getAttribute('data-activity-clear-one');
                const input = name ? document.querySelector(`input[name="${name}"]`) : null;
                if (input) {
                    input.value = '';
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        });

        const descInput = document.getElementById('activity-desc');
        const descEditorEl = document.querySelector('[data-activity-desc-editor]');
        if (descInput && descEditorEl && window.Quill) {
            const quill = new window.Quill(descEditorEl, {
                theme: 'snow',
                placeholder: @js(__('ui.activities.description_placeholder')),
                modules: {
                    toolbar: [
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }, 'blockquote', 'code-block'],
                        ['link'],
                        ['clean'],
                    ],
                },
            });

            const initialHtml = (descInput.value || '').trim();
            if (initialHtml) {
                quill.clipboard.dangerouslyPasteHTML(initialHtml);
            }

            quill.on('text-change', () => {
                const html = quill.root.innerHTML.trim();
                descInput.value = html === '<p><br></p>' ? '' : html;
            });
        }
    });
</script>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('activities.index') }}" class="btn btn-outline">
        {{ __('ui.common.cancel') }}
    </a>

    <x-button class="btn-primary" type="submit">{{ $submitLabel ?? __('ui.common.save') }}</x-button>
</div>
