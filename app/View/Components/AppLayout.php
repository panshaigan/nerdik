<?php

namespace App\View\Components;

use App\Support\Seo\SeoMetadata;
use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    public function __construct(public ?SeoMetadata $seo = null) {}

    /**
     * Get the view / contents that represents the component.
     */
    #[\Override]
    public function render(): View
    {
        return view('layouts.app');
    }
}
