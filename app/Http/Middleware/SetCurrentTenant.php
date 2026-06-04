<?php

namespace App\Http\Middleware;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Identity\Support\TenantResolver;
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
        if ($user) {
            $tenant = app(TenantResolver::class)
                ->resolveFor($user, $request->session()->get('active_tenant_id'));
            if ($tenant) {
                app(CurrentTenant::class)->set($tenant);
            }
        }

        return $next($request);
    }
}
