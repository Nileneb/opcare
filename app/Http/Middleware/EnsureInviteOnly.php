<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EnsureInviteOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('register') || $request->is('register')) {
            abort(403, 'Registrierung nur per Einladung möglich.');
        }

        return $next($request);
    }
}
