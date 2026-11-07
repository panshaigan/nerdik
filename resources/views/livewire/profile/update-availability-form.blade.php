<?php

use App\Enums\AvailabilityStatus;
use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Services\UserAvailabilityService;
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

    public string $exceptionDate = '';

    public string $exceptionStatus = 'available';

    /** @var list<array{id: int, date: string, status: string, status_label: string}> */
    public array $exceptions = [];

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

        $this->exceptions = $availability->upcomingExceptions($user)
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'date' => $row->date->toDateString(),
                'status' => $row->status->value,
                'status_label' => $row->status->label(),
            ])
            ->values()
            ->all();
    }

    public function saveWeekdays(UserAvailabilityService $availability): void
    {
        $this->reportProfileTabValidation('availability', function () use ($availability): void {
            $user = Auth::user();
            if ($user === null) {
                return;
            }

            $availability->saveWeekdayDefaults($user, $this->weekdays);
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
                'exceptionDate' => ['required', 'date', 'after_or_equal:today'],
                'exceptionStatus' => ['required', 'in:'.implode(',', AvailabilityStatus::values())],
            ]);

            $status = AvailabilityStatus::from($this->exceptionStatus);
            $availability->upsertDateException($user, \Illuminate\Support\Carbon::parse($this->exceptionDate), $status);

            $this->exceptionDate = '';
            $this->exceptionStatus = AvailabilityStatus::Available->value;
            $this->mount($availability);
        });
    }

    public function removeException(int $exceptionId, UserAvailabilityService $availability): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $row = $user->availabilityDateExceptions()->whereKey($exceptionId)->first();
        if ($row === null) {
            return;
        }

        $availability->removeDateException($user, $row->date);
        $this->mount($availability);
    }
}; ?>

<section id="ui-profile-availability-section" class="ui-profile-section" data-ui="profile-availability-section">
    <header class="mb-6">
        <h2 class="text-lg font-medium text-base-content">
            {{ __('ui.profile.availability.title') }}
        </h2>
        <p class="mt-1 text-sm text-base-content/70">
            {{ __('ui.profile.availability.intro') }}
        </p>
    </header>

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4" />

    <form wire:submit="saveWeekdays" class="space-y-6" data-ui="profile-availability-weekdays-form">
        <fieldset>
            <legend class="text-sm font-semibold text-base-content">
                {{ __('ui.profile.availability.weekday_defaults') }}
            </legend>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                @foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day)
                    <label class="label cursor-pointer justify-start gap-3 rounded-lg border border-base-300 bg-base-100 px-3 py-2">
                        <input
                            type="checkbox"
                            class="checkbox checkbox-sm checkbox-primary"
                            wire:model.live="weekdays.{{ $day }}"
                        />
                        <span class="label-text">{{ __('ui.profile.availability.weekday_'.$day) }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <x-button type="submit" class="btn-primary" spinner="saveWeekdays">
            {{ __('ui.common.save') }}
        </x-button>
    </form>

    <div class="divider my-8"></div>

    <form wire:submit="addException" class="space-y-4" data-ui="profile-availability-exception-form">
        <h3 class="text-sm font-semibold text-base-content">
            {{ __('ui.profile.availability.exceptions_title') }}
        </h3>
        <p class="text-sm text-base-content/70">
            {{ __('ui.profile.availability.exceptions_intro') }}
        </p>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <x-input
                type="date"
                wire:model="exceptionDate"
                :label="__('ui.profile.availability.exception_date')"
                class="flex-1"
            />
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
                class="flex-1"
            />
            <x-button type="submit" class="btn-secondary" spinner="addException">
                {{ __('ui.profile.availability.add_exception') }}
            </x-button>
        </div>
    </form>

    @if ($exceptions !== [])
        <ul class="mt-4 space-y-2" role="list" data-ui="profile-availability-exception-list">
            @foreach ($exceptions as $exception)
                <li wire:key="availability-exception-{{ $exception['id'] }}" class="flex items-center justify-between gap-3 rounded-lg border border-base-300 bg-base-100 px-3 py-2">
                    <div class="min-w-0 text-sm">
                        <span class="font-medium">{{ \Illuminate\Support\Carbon::parse($exception['date'])->translatedFormat('j M Y') }}</span>
                        <span class="text-base-content/70">— {{ $exception['status_label'] }}</span>
                    </div>
                    <x-button
                        type="button"
                        class="btn-ghost btn-square btn-sm text-error"
                        icon="o-trash"
                        wire:click="removeException({{ $exception['id'] }})"
                        :tooltip="__('ui.common.delete')"
                        :aria-label="__('ui.common.delete')"
                    />
                </li>
            @endforeach
        </ul>
    @endif
</section>
