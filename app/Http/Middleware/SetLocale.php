<?php

namespace App\Http\Middleware;

use App\Enums\AppLocale;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale');

        if (! AppLocale::isSupported(is_string($locale) ? $locale : null)) {
            $cookieLocale = $request->cookie('locale');
            if (AppLocale::isSupported(is_string($cookieLocale) ? $cookieLocale : null)) {
                $locale = $cookieLocale;
                $request->session()->put('locale', $cookieLocale);
            }
        }

        if (! AppLocale::isSupported(is_string($locale) ? $locale : null)) {
            $user = $request->user();
            if ($user instanceof User && $user->locale instanceof AppLocale) {
                $locale = $user->locale->value;
                $request->session()->put('locale', $locale);
            }
        }

        if (AppLocale::isSupported(is_string($locale) ? $locale : null)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
