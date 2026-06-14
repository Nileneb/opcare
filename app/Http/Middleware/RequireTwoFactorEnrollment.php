<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Track B (MFA-Pflicht für alle): Erzwingt das 2FA-Enrollment. Ein eingeloggter Benutzer ohne
 * abgeschlossenes Enrollment (`two_factor_confirmed_at === null`) wird auf die Enrollment-Seite
 * umgeleitet — bis dahin ist kein Zugriff auf die App möglich (außer Enrollment + Logout).
 *
 * Die Login-Challenge selbst läuft VOR der Authentifizierung (Session-Hand-off in der Login-Komponente),
 * daher braucht es hier nur das Enrollment-Gate.
 */
class RequireTwoFactorEnrollment
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (config('app.disable_two_factor')) {
            return $next($request);
        }

        if ($user !== null && $user->two_factor_confirmed_at === null
            && ! $request->routeIs('two-factor.enroll')
            && ! $request->routeIs('logout')) {
            return redirect()->route('two-factor.enroll');
        }

        return $next($request);
    }
}
