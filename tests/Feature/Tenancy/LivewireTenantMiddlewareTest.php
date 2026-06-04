<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Http\Request;

it('registriert SetCurrentTenant in der web-Middleware-Gruppe', function () {
    // WHY: Livewire-Update-Requests laufen nur durch die web-Gruppe. Ohne diesen Eintrag
    // ist CurrentTenant bei Livewire-Aktionen ungesetzt → tenant_id NULL.
    $groups = app('router')->getMiddlewareGroups();

    expect($groups['web'])->toContain(SetCurrentTenant::class);
});

it('setzt den Mandanten aus dem eingeloggten Nutzer (Middleware-Verhalten)', function () {
    $tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    $request->setUserResolver(fn () => $user);

    app(SetCurrentTenant::class)->handle($request, fn () => response('ok'));

    expect(app(CurrentTenant::class)->id())->toBe($tenant->id);
});
