<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveUser
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('logout') || $request->routeIs('logout')) {
            return $next($request);
        }

        abort_unless($request->user()?->active, 403);
        return $next($request);
    }
}
