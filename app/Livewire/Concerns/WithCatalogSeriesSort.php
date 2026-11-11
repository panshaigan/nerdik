<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

trait WithCatalogSeriesSort
{
    /** `popularity` | `activities` | `name` */
    #[Url(except: 'popularity')]
    public string $sort = 'popularity';

    /** `asc` | `desc` */
    #[Url(except: 'desc')]
    public string $sort_dir = 'desc';

    public function toggleSort(string $field): void
    {
        if (! in_array($field, ['popularity', 'activities', 'name'], true)) {
            return;
        }

        if ($this->catalogSeriesSortKey() === $field) {
            $this->sort_dir = $this->catalogSeriesSortDirection() === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $field;
            $this->sort_dir = match ($field) {
                'name' => 'asc',
                default => 'desc',
            };
        }

        $this->resetPage();
    }

    public function updatedSort(): void
    {
        if (! in_array($this->sort, ['popularity', 'activities', 'name'], true)) {
            $this->sort = 'popularity';
        }

        $this->sort_dir = match ($this->catalogSeriesSortKey()) {
            'name' => 'asc',
            default => 'desc',
        };

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

    protected function catalogSeriesSortDirection(): string
    {
        return strtolower($this->sort_dir) === 'asc' ? 'asc' : 'desc';
    }

    protected function catalogSeriesSortKey(): string
    {
        return in_array($this->sort, ['popularity', 'activities', 'name'], true) ? $this->sort : 'popularity';
    }
}
