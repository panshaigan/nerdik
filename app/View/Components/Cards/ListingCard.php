<?php

declare(strict_types=1);

namespace App\View\Components\Cards;

use App\Models\Activity;
use App\Models\Event;
use App\Support\Ui\BrowseListingCardPresenter;
use App\Support\Ui\BrowseListingCardViewData;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ListingCard extends Component
{
    public BrowseListingCardViewData $viewData;

    /**
     * @param  list<int>  $interestedIds
     */
    public function __construct(
        public Activity|Event $listing,
        public array $interestedIds = [],
        public ?string $returnUrl = null,
    ) {
        $presenter = app(BrowseListingCardPresenter::class);
        $this->viewData = $listing instanceof Event
            ? $presenter->fromEvent($listing, $interestedIds, $returnUrl)
            : $presenter->fromActivity($listing, $interestedIds, $returnUrl);
    }

    #[\Override]
    public function render(): View|Closure|string
    {
        return view('components.cards.listing-card');
    }
}
