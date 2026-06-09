<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Support\Seo\Seo;
use App\Support\Seo\SeoMetadata;
use Illuminate\View\View;

final class SeoComposer
{
    public function compose(View $view): void
    {
        if (($view->getData()['seo'] ?? null) instanceof SeoMetadata) {
            return;
        }

        if ($view->name() === 'welcome') {
            $view->with('seo', Seo::forWelcome());

            return;
        }

        $view->with('seo', Seo::fromCurrentRoute());
    }
}
