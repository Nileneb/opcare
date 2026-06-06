<?php

use App\Domains\Accounting\Actions\Buchen;
use App\Domains\Accounting\Enums\KontoTyp;
use App\Domains\Accounting\Models\Konto;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $this->wareneingang = Konto::create(['nummer' => '5400', 'name' => 'Wareneingang Küche', 'typ' => KontoTyp::Aufwand]);
    $this->verbindlichkeit = Konto::create(['nummer' => '1600', 'name' => 'Verbindlichkeiten', 'typ' => KontoTyp::Passiv]);
    $this->kasse = Konto::create(['nummer' => '1000', 'name' => 'Kasse', 'typ' => KontoTyp::Aktiv]);
});

it('bucht Soll an Haben und berechnet den Saldo nach Kontoart', function () {
    app(Buchen::class)->handle($this->wareneingang->id, $this->verbindlichkeit->id, 100.0, 'Wareneinkauf', '2026-06-08');

    expect($this->wareneingang->saldo())->toBe(100.0) // Aufwand: Soll-Seite
        ->and($this->verbindlichkeit->saldo())->toBe(100.0); // Passiv: Haben-Seite
});

it('saldiert mehrere Buchungen korrekt', function () {
    app(Buchen::class)->handle($this->kasse->id, $this->verbindlichkeit->id, 200.0, 'Einzahlung', '2026-06-08');
    app(Buchen::class)->handle($this->wareneingang->id, $this->kasse->id, 50.0, 'Barzahlung', '2026-06-09');

    expect($this->kasse->saldo())->toBe(150.0); // Aktiv: 200 Soll − 50 Haben
});

it('verweigert ungültige Buchungen', function () {
    expect(fn () => app(Buchen::class)->handle($this->kasse->id, $this->kasse->id, 10.0, 'x', '2026-06-08'))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => app(Buchen::class)->handle($this->kasse->id, $this->wareneingang->id, -5.0, 'x', '2026-06-08'))
        ->toThrow(InvalidArgumentException::class);
});

it('ist mandantengetrennt', function () {
    $fremd = Tenant::create(['name' => 'B', 'slug' => 'b']);
    app(CurrentTenant::class)->set($fremd);

    expect(Konto::count())->toBe(0);
});
