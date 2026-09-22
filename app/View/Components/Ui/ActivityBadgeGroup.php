<?php

namespace App\View\Components\Ui;

use App\Domain\ActivityBadges\ActivityBadgeItem;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ActivityBadgeGroup extends Component
{
    public int $collapseAfter;

    /**
     * @param  array<int, ActivityBadgeItem>  $items
     * @param  int|null  $collapseAfter  Max badges before show more/less; null uses config; 0 disables.
     */
    public function __construct(
        public array $items = [],
        public ?string $dataUi = null,
        ?int $collapseAfter = null,
    ) {
        $this->collapseAfter = $collapseAfter ?? (int) config('activity-badges.collapse_after', 6);
    }

    public function shouldCollapse(): bool
    {
        return $this->collapseAfter > 0 && count($this->items) > $this->collapseAfter;
    }

    #[\Override]
    public function render(): View|Closure|string
    {
        return view('components.activity-badges.group');
    }
}
