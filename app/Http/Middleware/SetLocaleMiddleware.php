<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Définit la locale de l'application selon la préférence de l'utilisateur connecté.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? config('app.locale', 'fr');

        if (in_array($locale, config('app.available_locales', ['fr', 'en']))) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
