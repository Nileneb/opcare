<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Scheduling\Enums\WunschTyp;
use App\Domains\Scheduling\Models\Dienstwunsch;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
});

it('legt einen Dienstwunsch mit Typ an', function () {
    $w = Dienstwunsch::create(['user_id' => $this->user->id, 'datum' => '2026-06-15', 'typ' => WunschTyp::Frei, 'notiz' => 'Geburtstag']);

    expect($w->typ)->toBe(WunschTyp::Frei)
        ->and($w->typ->label())->toBe('möchte frei')
        ->and($w->user->id)->toBe($this->user->id);
});

it('ist mandantengetrennt', function () {
    Dienstwunsch::create(['user_id' => $this->user->id, 'datum' => '2026-06-15', 'typ' => WunschTyp::Arbeiten]);
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremd);

    expect(Dienstwunsch::count())->toBe(0);
});
