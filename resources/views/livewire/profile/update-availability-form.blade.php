<?php

use App\Enums\AvailabilityStatus;
use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Models\Tag;
use App\Services\UserAvailabilityService;
use App\Services\UserPlayPreferencesService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
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

    public function interestTagsForSelector(): Collection
    {
        return Tag::query()->forBrowseSelector($this->tag_ids);
    }

    public function updatedPlayLatitude(): void
    {
        $this->persistPlayAreaIfComplete();
        $this->skipRender();
    }

    public function updatedPlayLongitude(): void
    {
        $this->persistPlayAreaIfComplete();
        $this->skipRender();
    }

    public function updatedPlayRadiusKm(): void
    {
        $this->persistPlayAreaIfComplete();
        $this->skipRender();
    }

    public function updatedPlayLabel(): void
    {
        $this->persistPlayAreaIfComplete();
        $this->skipRender();
    }

    public function updatedTagIds(): void
    {
        $this->persistInterestTags();
        $this->skipRender();
    }

    public function updatedNewTags(): void
    {
        $this->persistInterestTags();
        $this->skipRender();
    }

    /**
     * @param  list<int|string>  $tag_ids
     * @param  list<array{label: string, category_id: int}>  $new_tags
     */
    public function syncBrowseTagsFromSelector(array $tag_ids, array $new_tags = []): void
    {
        $this->tag_ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $tag_ids),
            static fn (int $id) => $id > 0
        )));

        $this->new_tags = collect($new_tags)
            ->filter(static fn ($row) => is_array($row) && isset($row['label'], $row['category_id']))
            ->map(static fn (array $row) => [
                'label' => trim((string) $row['label']),
                'category_id' => (int) $row['category_id'],
            ])
            ->values()
            ->all();

        $this->persistInterestTags();
        $this->skipRender();
    }

    public function clearPlayArea(UserPlayPreferencesService $preferences): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $preferences->clearPlayArea($user);
        $this->playLatitude = null;
        $this->playLongitude = null;
        $this->playRadiusKm = UserPlayPreferencesService::DEFAULT_RADIUS_KM;
        $this->playLabel = null;
        $this->dispatch('profile-play-area-cleared');
        $this->skipRender();
    }

    private function persistPlayAreaIfComplete(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        if ($this->playLatitude === null || $this->playLongitude === null) {
            return;
        }

        try {
            $this->reportProfileTabValidation('availability', function () use ($user): void {
                app(UserPlayPreferencesService::class)->savePlayArea(
                    $user,
                    (float) $this->playLatitude,
                    (float) $this->playLongitude,
                    (int) $this->playRadiusKm,
                    $this->playLabel,
                );
                $this->dispatch('profile-availability-updated');
            });
        } catch (ValidationException) {
            // Errors surface via profile tab validation badge.
        }
    }

    private function persistInterestTags(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        try {
            $this->reportProfileTabValidation('availability', function () use ($user): void {
                $this->validate([
                    'tag_ids' => ['nullable', 'array'],
                    'tag_ids.*' => ['integer', 'exists:tags,id'],
                    'new_tags' => ['nullable', 'array'],
                    'new_tags.*.label' => ['nullable', 'string', 'max:255'],
                    'new_tags.*.category_id' => ['nullable', 'integer', 'exists:tag_categories,id'],
                ]);

                app(UserPlayPreferencesService::class)->syncInterestTags(
                    $user,
                    $this->tag_ids,
                    $this->new_tags,
                );
                $this->dispatch('profile-availability-updated');
            });
        } catch (ValidationException) {
            // Errors surface via profile tab validation badge.
        }
    }

    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'weekdays')) {
            return;
        }

        $user = Auth::user();
        if ($user === null) {
            return;
        }

        app(UserAvailabilityService::class)->saveWeekdayDefaults($user, $this->weekdays);
        $this->dispatch('profile-availability-updated');
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
                        wire:model.live="weekdays.{{ $day }}"
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
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-sm font-semibold text-base-content">
                    {{ __('ui.profile.availability.play_area_title') }}
                </h3>
                <p class="mt-1 text-xs text-base-content/70">
                    {{ __('ui.profile.availability.play_area_hint') }}
                </p>
            </div>
            @if ($playLatitude !== null && $playLongitude !== null)
                <x-button
                    type="button"
                    class="btn-ghost btn-sm text-error"
                    wire:click="clearPlayArea"
                    spinner="clearPlayArea"
                    :tooltip="__('ui.profile.availability.play_area_clear')"
                    :aria-label="__('ui.profile.availability.play_area_clear')"
                >
                    {{ __('ui.profile.availability.play_area_clear') }}
                </x-button>
            @endif
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
        <div wire:ignore class="overflow-visible">
            @include('tags.partials.selector', [
                'tags' => $this->interestTagsForSelector(),
                'selectedIds' => $tag_ids,
                'allowCreate' => true,
                'browseTagSelector' => true,
                'placeholder' => __('ui.profile.availability.interest_tags_placeholder'),
            ])
        </div>
        <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
        <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
        <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
        <x-field-error :messages="$errors->get('new_tags.*.category_id')" class="mt-2" />
    </div>
</section>
