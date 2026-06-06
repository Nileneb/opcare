<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vertretungen mit reinem Portal-Konto (Rollen betreuer/angehoeriger) dürfen ausschließlich ihr
 * read-only Portal, Profil, 2FA-Enrollment und Logout erreichen. WHY(IDOR/DSGVO): ohne diese Schranke
 * würde ein Betreuer-Login die Staff-Routen (Bewohnerliste, SIS, Medikation …) und damit fremde
 * Gesundheitsdaten sehen — die Aufgabenkreis-Begrenzung des Portals gilt sonst nur in der UI, nicht serverseitig.
 */
class RestrictPortalUsers
{
    private const ERLAUBT = ['portal', 'profile', 'logout', 'two-factor.enroll'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null
            && $user->hasAnyRole(['betreuer', 'angehoeriger'])
            && ! $user->hasAnyRole(['admin', 'pflegefachkraft', 'pflegehilfskraft', 'haustechnik', 'kueche',
                'betreuungskraft', 'buchhaltung', 'leserecht', 'super-admin'])
            && ! collect(self::ERLAUBT)->contains(fn (string $name): bool => $request->routeIs($name))) {
            return redirect()->route('portal');
        }

        return $next($request);
    }
}
