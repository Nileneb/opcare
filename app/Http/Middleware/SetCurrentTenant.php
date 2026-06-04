<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Support\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt den Mandanten-Kontext aus dem eingeloggten Benutzer. WHY: der globale
 * TenantScope greift erst, wenn CurrentTenant gesetzt ist — ohne diesen Schritt
 * liefen Queries mandantenübergreifend.
 */
class SetCurrentTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant) {
            app(CurrentTenant::class)->set($user->tenant);
        }

        return $next($request);
    }
}
