<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt Sicherheits-HTTP-Header (Track B). Defensive-in-depth gegen Clickjacking, MIME-Sniffing,
 * Referrer-Leaks und Cross-Origin-Skript-/Frame-Injektion.
 *
 * WHY(CSP): Livewire 4 + Alpine.js brauchen `'unsafe-inline'` (injizierte Snapshots/Direktiven) und
 * `'unsafe-eval'` (Alpine wertet Ausdrücke zur Laufzeit aus) — ohne sie bricht die gesamte UI. Die CSP
 * begrenzt trotzdem die Herkunft auf `'self'` und blockiert externe Skript-/Frame-/Form-Ziele.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // WHY(OWASP): Der Legacy-XSS-Filter ist fehleranfällig → explizit deaktivieren, CSP übernimmt.
            'X-XSS-Protection' => '0',
            // Mikrofon für das Speech-/Transkriptions-Feature (self), alles andere aus.
            'Permissions-Policy' => 'camera=(), microphone=(self), geolocation=(), payment=(), usb=()',
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "media-src 'self' blob:",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]),
        ];

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        // WHY: HSTS nur über TLS senden (per Spec ignorieren Browser es sonst); greift in Prod hinter HTTPS.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}
