<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SentEmail;
use Illuminate\Http\Response;

class SentEmailPreviewController extends Controller
{
    public function __invoke(SentEmail $sentEmail): Response
    {
        $html = $sentEmail->htmlBody();
        abort_if($html === null, 404);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; img-src https: http: data:; style-src 'unsafe-inline'; font-src https: data:; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }
}
