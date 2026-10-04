<?php

namespace App\Http\Middleware;

use Closure;

class SetLocale
{
    public function handle($request, Closure $next)
    {
        $locale = substr($request->header('Accept-Language', 'el'), 0, 2);
        app()->setLocale(in_array($locale, ['el', 'en']) ? $locale : 'el');

        return $next($request);
    }
}
