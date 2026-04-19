<?php

namespace App\View\Components\Ui;

use App\Dto\Ui\ActivityBadgeItem;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ActivityBadgeGroup extends Component
{
    /**
     * @param  array<int, ActivityBadgeItem>  $items
     */
    public function __construct(
        public array $items = [],
        public ?string $dataUi = null,
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.ui.activity-badge-group');
    }
}
