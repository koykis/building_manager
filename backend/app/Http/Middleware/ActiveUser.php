<?php

namespace App\Http\Middleware;

use Closure;

class ActiveUser
{
    public function handle($request, Closure $next)
    {
        abort_unless($request->user()?->active, 403);

        return $next($request);
    }
}
