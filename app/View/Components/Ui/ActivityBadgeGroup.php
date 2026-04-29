<?php

namespace App\View\Components\Ui;

use App\Domain\ActivityBadges\ActivityBadgeItem;
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

    #[\Override]
    public function render(): View|Closure|string
    {
        return view('components.activity-badges.group');
    }
}
