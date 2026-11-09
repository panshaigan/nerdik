<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Services\GroupAvailabilityService;
use App\Services\GroupCalendarActivityPrefillService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class GroupAvailabilityCalendar extends Component
{
    #[Locked]
    public int $groupId;

    public string $month;

    public int $minAvailable = 0;

    public ?string $selectedDate = null;

    public bool $dayDetailOpen = false;

    public function mount(int $groupId, ?string $month = null): void
    {
        $this->groupId = $groupId;
        $this->month = $month ?? now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = Carbon::parse($this->month.'-01')->subMonth()->format('Y-m');
        $this->selectedDate = null;
        $this->dayDetailOpen = false;
    }

    public function nextMonth(): void
    {
        $this->month = Carbon::parse($this->month.'-01')->addMonth()->format('Y-m');
        $this->selectedDate = null;
        $this->dayDetailOpen = false;
    }

    public function selectDate(string $date): void
    {
        $this->selectedDate = $date;
        $this->dayDetailOpen = true;
    }

    public function closeDayDetail(): void
    {
        $this->selectedDate = null;
        $this->dayDetailOpen = false;
    }

    public function updatedMinAvailable(mixed $value): void
    {
        $this->minAvailable = max(0, (int) $value);
    }

    public function isFilterActive(): bool
    {
        return $this->minAvailable > 0;
    }

    public function createActivityUrlForSelectedDate(Group $group): ?string
    {
        if ($this->selectedDate === null) {
            return null;
        }

        $dayStart = Carbon::parse($this->selectedDate, display_timezone())->startOfDay();
        $todayStart = Carbon::now(display_timezone())->startOfDay();
        if ($dayStart->lessThan($todayStart)) {
            return null;
        }

        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        $token = app(GroupCalendarActivityPrefillService::class)->makeToken($group, $this->selectedDate, $user);

        return url_with_return(
            route('activities.create', ['group_calendar' => $token]),
            route('groups.show', $group, false),
        );
    }

    public function render(GroupAvailabilityService $calendar)
    {
        $group = Group::query()->memberContentVisibleTo(Auth::user())->with('tags')->whereKey($this->groupId)->firstOrFail();

        $monthDate = Carbon::parse($this->month.'-01');
        $minAvailable = $this->minAvailable > 0 ? $this->minAvailable : null;
        $summary = $calendar->monthSummary(
            $group,
            $monthDate,
            $minAvailable,
            requireAll: false,
        );

        $start = $monthDate->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $monthDate->copy()->endOfMonth()->endOfWeek(Carbon::MONDAY);
        $weeks = [];
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($end)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $dateKey = $cursor->toDateString();
                $week[] = [
                    'date' => $dateKey,
                    'in_month' => $cursor->isSameMonth($monthDate),
                    'counts' => $summary['days'][$dateKey] ?? [
                        'available' => 0,
                        'unavailable' => 0,
                        'uncertain' => 0,
                        'total' => count($summary['member_ids']),
                        'matches_filter' => false,
                    ],
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        $dayDetail = null;
        if ($this->selectedDate !== null) {
            $dayDetail = $calendar->dayDetail($group, Carbon::parse($this->selectedDate));
        }

        return view('livewire.groups.group-availability-calendar', [
            'group' => $group,
            'weeks' => $weeks,
            'monthLabel' => $monthDate->translatedFormat('F Y'),
            'dayDetail' => $dayDetail,
            'memberCount' => count($summary['member_ids']),
            'filterActive' => $this->isFilterActive(),
        ]);
    }
}
