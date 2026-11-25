<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\PageAliasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PageAliasRedirectController extends Controller
{
    public function __invoke(Request $request, PageAliasService $pageAliases): RedirectResponse|Response
    {
        $path = trim($request->path(), '/');

        if ($path === '' || str_contains($path, '/')) {
            abort(404);
        }

        $alias = $pageAliases->resolve($path);

        if ($alias === null) {
            abort(404);
        }

        $alias->increment('hits');

        return redirect($alias->target_path, 302);
    }
}
