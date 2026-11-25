<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;

trait WithSeriesShowPast
{
    #[Url(as: 'show_past', except: false)]
    public bool $showPast = false;

    public function toggleShowPast(): void
    {
        $this->showPast = ! $this->showPast;
    }
}
