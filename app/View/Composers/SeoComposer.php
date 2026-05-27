<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Support\Seo\Seo;
use Illuminate\View\View;

final class SeoComposer
{
    public function compose(View $view): void
    {
        if ($view->offsetExists('seo')) {
            return;
        }

        if ($view->name() === 'welcome') {
            $view->with('seo', Seo::forWelcome());

            return;
        }

        $view->with('seo', Seo::fromCurrentRoute());
    }
}
