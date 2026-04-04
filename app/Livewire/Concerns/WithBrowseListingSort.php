<?php

namespace App\Livewire\Concerns;

use App\Models\Activity;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

trait WithBrowseListingSort
{
    /** `name` | `date` */
    #[Url]
    public string $sort = 'date';

    /** `asc` | `desc` */
    #[Url]
    public string $sort_dir = 'desc';

    public function updatedSort(): void
    {
        if (! in_array($this->sort, ['name', 'date'], true)) {
            $this->sort = 'date';
        }
        $this->resetPage();
    }

    public function updatedSortDir(): void
    {
        if (! in_array(strtolower($this->sort_dir), ['asc', 'desc'], true)) {
            $this->sort_dir = 'desc';
        } else {
            $this->sort_dir = strtolower($this->sort_dir);
        }
        $this->resetPage();
    }

    protected function browseSortDirection(): string
    {
        return strtolower($this->sort_dir) === 'asc' ? 'asc' : 'desc';
    }

    protected function browseSortKey(): string
    {
        return in_array($this->sort, ['name', 'date'], true) ? $this->sort : 'date';
    }

    /**
     * @param  Builder<Event>  $query
     */
    protected function applyBrowseEventSort(Builder $query): void
    {
        $dir = $this->browseSortDirection();
        if ($this->browseSortKey() === 'name') {
            $query->orderBy('events.name', $dir);
        } else {
            $query->orderBy('events.starts_at', $dir);
        }
        $query->orderBy('events.id', $dir);
    }

    /**
     * @param  Builder<Activity>  $query
     */
    protected function applyBrowseActivitySort(Builder $query): void
    {
        $dir = $this->browseSortDirection();
        if ($this->browseSortKey() === 'name') {
            $query->orderBy('activities.name', $dir);
        } else {
            $query->orderByRaw(
                '(select slots.starts_at from slots where slots.activity_id = activities.id limit 1) '.$dir
            );
        }
        $query->orderBy('activities.id', $dir);
    }
}
