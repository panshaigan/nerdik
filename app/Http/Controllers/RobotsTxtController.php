<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Seo\RobotsTxt;
use Symfony\Component\HttpFoundation\Response;

final class RobotsTxtController extends Controller
{
    public function __invoke(RobotsTxt $robotsTxt): Response
    {
        return response($robotsTxt->body(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
