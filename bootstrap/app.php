<?php

use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => SetCurrentTenant::class,
        ]);

        // WHY: Livewire-Update-Requests (POST /livewire/update) laufen nur durch die
        // web-Gruppe, nicht durch die Route-Middleware der Seiten. Ohne diesen Eintrag
        // ist CurrentTenant bei Livewire-Aktionen ungesetzt → tenant_id NULL beim Anlegen
        // tenant-skopierter Modelle. Läuft am Ende der Gruppe (nach StartSession/Auth).
        $middleware->web(append: [
            SetCurrentTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // WHY: fetch()-Calls (z. B. /speech/*) senden Accept: application/json — Fehler
        // (Validation 422, Auth 401) müssen dann als JSON kommen, nicht als HTML-Redirect.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
