<?php

use App\Enums\AvailabilityStatus;
use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Services\TagSelectionService;
use App\Services\UserAvailabilityService;
use App\Services\UserPlayPreferencesService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;

    /** @var array{mon: bool, tue: bool, wed: bool, thu: bool, fri: bool, sat: bool, sun: bool} */
    public array $weekdays = [
        'mon' => false,
        'tue' => false,
        'wed' => false,
        'thu' => false,
        'fri' => false,
        'sat' => false,
        'sun' => false,
    ];

    public string $exceptionFrom = '';

    public string $exceptionTo = '';

    public string $exceptionStatus = 'available';

    /** @var list<array{from: string, to: string, status: string, status_label: string}> */
    public array $exceptionRanges = [];

    public ?float $playLatitude = null;

    public ?float $playLongitude = null;

    public int $playRadiusKm = UserPlayPreferencesService::DEFAULT_RADIUS_KM;

    public ?string $playLabel = null;

    /** @var list<int> */
    public array $tag_ids = [];

    /** @var list<array{label?: string, category_id?: int}> */
    public array $new_tags = [];

    public function mount(UserAvailabilityService $availability): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $defaults = $user->availabilityWeekday;
        if ($defaults !== null) {
            foreach (array_keys($this->weekdays) as $day) {
                $this->weekdays[$day] = (bool) $defaults->{$day};
            }
        }

        $this->exceptionRanges = $availability->groupExceptionsIntoRanges(
            $availability->upcomingExceptions($user),
        );

        $this->hydratePlayPreferences($user);
    }

    private function hydratePlayPreferences(\App\Models\User $user): void
    {
        $user->loadMissing(['playArea', 'interestTags']);

        $playArea = $user->playArea;
        if ($playArea !== null) {
            $this->playLatitude = (float) $playArea->latitude;
            $this->playLongitude = (float) $playArea->longitude;
            $this->playRadiusKm = (int) $playArea->radius_km;
            $this->playLabel = $playArea->label;
        } else {
            $this->playLatitude = null;
            $this->playLongitude = null;
            $this->playRadiusKm = UserPlayPreferencesService::DEFAULT_RADIUS_KM;
            $this->playLabel = null;
        }

        $this->tag_ids = $user->interestTags
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $this->new_tags = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlayAreaMapConfigProperty(): array
    {
        return [
            'searchUrl' => route('geocode.search'),
            'reverseUrl' => route('geocode.reverse'),
            'latitude' => $this->playLatitude,
            'longitude' => $this->playLongitude,
            'radiusKm' => $this->playRadiusKm,
            'label' => $this->playLabel,
            'minRadiusKm' => UserPlayPreferencesService::MIN_RADIUS_KM,
            'maxRadiusKm' => UserPlayPreferencesService::MAX_RADIUS_KM,
            'defaultRadiusKm' => UserPlayPreferencesService::DEFAULT_RADIUS_KM,
            'strings' => [
                'noLocation' => (string) __('ui.profile.availability.play_area_no_label'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getInterestTagPickerConfigProperty(): array
    {
        $locale = app()->getLocale();
        $tagSelection = app(TagSelectionService::class);

        return [
            'locale' => $locale,
            'semanticByTagCategory' => config('activity-badges.semantic_by_tag_category', []),
            'defaultTaxonomySemantic' => config('activity-badges.semantic_by_kind.taxonomy_tag', 'neutral'),
            'categories' => TagCategory::query()
                ->with('translations')
                ->orderBy('key')
                ->get()
                ->map(static fn (TagCategory $cat) => [
                    'id' => (int) $cat->id,
                    'key' => (string) $cat->key,
                    'name' => (string) $cat->name($locale),
                ])
                ->values()
                ->all(),
            'tags' => $tagSelection->tagsPayloadForActivityPicker(
                Tag::query()->forActivityFormPicker()->get(),
                $locale,
            ),
            'initialSelectedIds' => $this->tag_ids,
            'initialNewTags' => $this->new_tags,
            'ignoreActivityTypeContexts' => true,
            'strings' => [
                'createTag' => __('ui.tags.create_tag'),
                'auto' => __('ui.tags.auto'),
            ],
        ];
    }

    public function setPlayAreaDraft(
        ?float $latitude,
        ?float $longitude,
        int $radiusKm,
        ?string $label,
    ): void {
        $this->playLatitude = $latitude;
        $this->playLongitude = $longitude;
        $this->playRadiusKm = max(
            UserPlayPreferencesService::MIN_RADIUS_KM,
            min(UserPlayPreferencesService::MAX_RADIUS_KM, $radiusKm),
        );
        $normalized = $label !== null ? trim($label) : null;
        $this->playLabel = $normalized === '' ? null : $normalized;
        $this->skipRender();
    }

    public function updatedTagIds(): void
    {
        $this->skipRender();
    }

    public function updatedNewTags(): void
    {
        $this->skipRender();
    }

    public function save(
        UserAvailabilityService $availability,
        UserPlayPreferencesService $preferences,
    ): void {
        $this->reportProfileTabValidation('availability', function () use ($availability, $preferences): void {
            $user = Auth::user();
            if ($user === null) {
                return;
            }

            $this->validate([
                'weekdays.mon' => ['boolean'],
                'weekdays.tue' => ['boolean'],
                'weekdays.wed' => ['boolean'],
                'weekdays.thu' => ['boolean'],
                'weekdays.fri' => ['boolean'],
                'weekdays.sat' => ['boolean'],
                'weekdays.sun' => ['boolean'],
                'playLatitude' => ['nullable', 'numeric', 'between:-90,90'],
                'playLongitude' => ['nullable', 'numeric', 'between:-180,180'],
                'playRadiusKm' => [
                    'integer',
                    'min:'.UserPlayPreferencesService::MIN_RADIUS_KM,
                    'max:'.UserPlayPreferencesService::MAX_RADIUS_KM,
                ],
                'playLabel' => ['nullable', 'string', 'max:255'],
                'tag_ids' => ['nullable', 'array'],
                'tag_ids.*' => ['integer', 'exists:tags,id'],
                'new_tags' => ['nullable', 'array'],
                'new_tags.*.label' => ['nullable', 'string', 'max:255'],
                'new_tags.*.category_id' => ['nullable', 'integer', 'exists:tag_categories,id'],
            ]);

            $availability->saveWeekdayDefaults($user, $this->weekdays);

            if ($this->playLatitude !== null && $this->playLongitude !== null) {
                $preferences->savePlayArea(
                    $user,
                    (float) $this->playLatitude,
                    (float) $this->playLongitude,
                    (int) $this->playRadiusKm,
                    $this->playLabel,
                );
            } else {
                $preferences->clearPlayArea($user);
            }

            $preferences->syncInterestTags($user, $this->tag_ids, $this->new_tags);

            $this->new_tags = [];
            $this->dispatch('profile-availability-updated');
        });
    }

    public function addException(UserAvailabilityService $availability): void
    {
        $this->reportProfileTabValidation('availability', function () use ($availability): void {
            $user = Auth::user();
            if ($user === null) {
                return;
            }

            $this->validate([
                'exceptionFrom' => ['required', 'date', 'after_or_equal:today'],
                'exceptionTo' => ['required', 'date', 'after_or_equal:exceptionFrom'],
                'exceptionStatus' => ['required', 'in:'.implode(',', AvailabilityStatus::values())],
            ]);

            $status = AvailabilityStatus::from($this->exceptionStatus);
            $availability->upsertDateExceptionRange(
                $user,
                Carbon::parse($this->exceptionFrom),
                Carbon::parse($this->exceptionTo),
                $status,
            );

            $this->exceptionFrom = '';
            $this->exceptionTo = '';
            $this->exceptionStatus = AvailabilityStatus::Available->value;
            $this->mount($availability);
        });
    }

    public function removeExceptionRange(string $from, string $to, UserAvailabilityService $availability): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $availability->removeDateExceptionRange($user, Carbon::parse($from), Carbon::parse($to));
        $this->mount($availability);
    }
}; ?>

<section id="ui-profile-availability-section" class="ui-profile-section" data-ui="profile-availability-section">
    <header class="mb-6">
        <h2 class="text-lg font-medium text-base-content">
            {{ __('ui.profile.availability.title') }}
        </h2>
    </header>

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.form_errors')" icon="o-face-frown" class="!mx-0 mb-4" />

    <div class="space-y-3" data-ui="profile-availability-weekdays-form">
        <h3 class="text-sm font-semibold text-base-content">
            {{ __('ui.profile.availability.weekday_defaults') }}
        </h3>
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
            @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day)
                <label class="label cursor-pointer justify-start gap-3 rounded-lg border border-base-300 bg-base-100 px-3 py-2 transition duration-150 hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md hover:shadow-base-content/10">
                    <input
                        type="checkbox"
                        class="checkbox checkbox-sm checkbox-primary"
                        wire:model="weekdays.{{ $day }}"
                    />
                    <span class="label-text">{{ __('ui.profile.availability.weekday_'.$day) }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="divider my-8"></div>

    <form wire:submit="addException" class="space-y-4" data-ui="profile-availability-exception-form">
        <h3 class="text-sm font-semibold text-base-content">
            {{ __('ui.profile.availability.exceptions_title') }}
        </h3>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] sm:items-end">
            @include('livewire.profile.partials.availability-date-range', [
                'exceptionFrom' => $exceptionFrom,
                'exceptionTo' => $exceptionTo,
            ])
            <x-select
                wire:model="exceptionStatus"
                :label="__('ui.profile.availability.exception_status')"
                :options="[
                    ['id' => 'available', 'name' => __('ui.availability.status_available')],
                    ['id' => 'unavailable', 'name' => __('ui.availability.status_unavailable')],
                    ['id' => 'uncertain', 'name' => __('ui.availability.status_uncertain')],
                ]"
                option-value="id"
                option-label="name"
            />
            <x-button type="submit" class="btn-secondary" spinner="addException">
                {{ __('ui.profile.availability.add_exception') }}
            </x-button>
        </div>
    </form>

    @if ($exceptionRanges !== [])
        <ul class="mt-4 space-y-2" role="list" data-ui="profile-availability-exception-list">
            @foreach ($exceptionRanges as $range)
                @php
                    $fromLabel = format_date_in_user_tz($range['from']);
                    $toLabel = format_date_in_user_tz($range['to']);
                    $dateLabel = $range['from'] === $range['to']
                        ? $fromLabel
                        : $fromLabel.' – '.$toLabel;
                @endphp
                <li
                    wire:key="availability-exception-{{ $range['from'] }}-{{ $range['to'] }}-{{ $range['status'] }}"
                    class="flex items-center justify-between gap-3 rounded-lg border border-base-300 bg-base-100 px-3 py-2 transition duration-150 hover:-translate-y-0.5 hover:border-primary/50 hover:shadow-md hover:shadow-base-content/10"
                >
                    <div class="min-w-0 text-sm">
                        <span class="font-medium">{{ $dateLabel }}</span>
                        <span class="text-base-content/70">— {{ $range['status_label'] }}</span>
                    </div>
                    <x-button
                        type="button"
                        class="btn-ghost btn-square btn-sm text-error"
                        icon="o-trash"
                        wire:click="removeExceptionRange('{{ $range['from'] }}', '{{ $range['to'] }}')"
                        :tooltip="__('ui.common.delete')"
                        :aria-label="__('ui.common.delete')"
                    />
                </li>
            @endforeach
        </ul>
    @endif

    <div class="divider my-8"></div>

    <div class="space-y-4 overflow-visible" data-ui="profile-availability-play-area">
        <div>
            <h3 class="text-sm font-semibold text-base-content">
                {{ __('ui.profile.availability.play_area_title') }}
            </h3>
            <p class="mt-1 text-xs text-base-content/70">
                {{ __('ui.profile.availability.play_area_hint') }}
            </p>
        </div>
        @include('livewire.profile.partials.play-area-map', [
            'playAreaMapConfig' => $this->playAreaMapConfig,
        ])
    </div>

    <div class="divider my-8"></div>

    <div class="space-y-3 overflow-visible" data-ui="profile-availability-interest-tags">
        <div>
            <h3 class="text-sm font-semibold text-base-content">
                {{ __('ui.profile.availability.interest_tags_title') }}
            </h3>
            <p class="mt-1 text-xs text-base-content/70">
                {{ __('ui.profile.availability.interest_tags_hint') }}
            </p>
        </div>
        <x-activity.category-tags-picker :config="$this->interestTagPickerConfig" />
        <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
        <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
        <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
        <x-field-error :messages="$errors->get('new_tags.*.category_id')" class="mt-2" />
    </div>

    <div class="mt-8 flex items-center gap-3" data-ui="profile-availability-save">
        <x-action-message class="me-3" on="profile-availability-updated">{{ __('ui.common.saved') }}</x-action-message>
        <x-button
            type="button"
            class="btn-primary"
            wire:click="save"
            spinner="save"
        >
            {{ __('ui.common.save') }}
        </x-button>
    </div>
</section>
