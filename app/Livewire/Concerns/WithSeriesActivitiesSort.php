<?php

namespace App\Livewire\Concerns;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

trait WithSeriesActivitiesSort
{
    /** `date` | `host` | `name` */
    #[Url(except: 'date')]
    public string $sort = 'date';

    /** `asc` | `desc` */
    #[Url(except: 'asc')]
    public string $sort_dir = 'asc';

    public function toggleSort(string $field): void
    {
        if (! in_array($field, ['date', 'host', 'name'], true)) {
            return;
        }

        if ($this->seriesActivitiesSortKey() === $field) {
            $this->sort_dir = $this->seriesActivitiesSortDirection() === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $field;
            $this->sort_dir = 'asc';
        }
    }

    public function updatedSort(): void
    {
        if (! in_array($this->sort, ['date', 'host', 'name'], true)) {
            $this->sort = 'date';
        }
    }

    public function updatedSortDir(): void
    {
        if (! in_array(strtolower($this->sort_dir), ['asc', 'desc'], true)) {
            $this->sort_dir = 'asc';
        } else {
            $this->sort_dir = strtolower($this->sort_dir);
        }
    }

    protected function seriesActivitiesSortDirection(): string
    {
        return strtolower($this->sort_dir) === 'asc' ? 'asc' : 'desc';
    }

    protected function seriesActivitiesSortKey(): string
    {
        return in_array($this->sort, ['date', 'host', 'name'], true) ? $this->sort : 'date';
    }

    /**
     * @param  Builder<Activity>  $query
     */
    protected function applySeriesActivitySort(Builder $query): void
    {
        $dir = $this->seriesActivitiesSortDirection();

        match ($this->seriesActivitiesSortKey()) {
            'name' => $query->orderBy('activities.name', $dir),
            'host' => $query->orderByRaw(
                '(SELECT users.nickname FROM users WHERE users.id = activities.created_by LIMIT 1) '.$dir
            ),
            default => $query->orderByRaw(Activity::scheduleStartsAtSql().' '.$dir),
        };

        $query->orderBy('activities.id', $dir);
    }
}
