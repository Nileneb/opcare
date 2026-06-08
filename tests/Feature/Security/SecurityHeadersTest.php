<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('setzt die Sicherheits-Header auf Web-Antworten', function () {
    $response = $this->get(route('login'));

    $response->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('X-XSS-Protection', '0');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("form-action 'self'");
    expect($response->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('erlaubt den Reverb-WebSocket in connect-src (sonst blockt CSP den Echtzeit-Chat)', function () {
    $response = $this->get(route('login'));

    $host = config('reverb.servers.reverb.hostname')
        ?: (config('broadcasting.connections.reverb.options.host') ?: 'localhost');
    $port = config('broadcasting.connections.reverb.options.port', 443);

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("connect-src 'self' ws://{$host}:{$port} wss://{$host}:{$port}");
});

it('sendet HSTS nur über HTTPS', function () {
    $mw = new SecurityHeaders;
    $next = fn () => new Response('ok');

    $secure = $mw->handle(Request::create('https://opcare.test/x', 'GET'), $next);
    expect($secure->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000');

    $plain = $mw->handle(Request::create('http://opcare.test/x', 'GET'), $next);
    expect($plain->headers->has('Strict-Transport-Security'))->toBeFalse();
});
