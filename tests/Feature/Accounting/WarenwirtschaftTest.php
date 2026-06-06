<?php

use App\Domains\Accounting\Actions\Wareneingang;
use App\Domains\Accounting\Actions\Warenverbrauch;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);
    $this->mehl = Artikel::create(['name' => 'Mehl', 'einheit' => 'kg', 'abteilung' => Abteilung::Kueche, 'bestand' => 0, 'einkaufspreis' => 2.00]);
});

it('bucht den Wareneingang (Soll Warenbestand an Haben Verbindlichkeiten) und erhöht den Bestand', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');

    expect((float) $this->mehl->fresh()->bestand)->toBe(10.0)
        ->and(AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->saldo())->toBe(20.0)
        ->and(AccountingDefaults::konto(AccountingDefaults::VERBINDLICHKEITEN)->saldo())->toBe(20.0);
});

it('bucht den Verbrauch auf das Abteilungs-Aufwandskonto und mindert den Bestand', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    $bewegung = app(Warenverbrauch::class)->handle($this->mehl->fresh(), 4, '2026-06-09');

    expect((float) $this->mehl->fresh()->bestand)->toBe(6.0)
        ->and(AccountingDefaults::konto(Abteilung::Kueche->aufwandKonto())->saldo())->toBe(8.0) // 4 × 2,00
        ->and(AccountingDefaults::konto(AccountingDefaults::WARENBESTAND)->saldo())->toBe(12.0) // 20 − 8
        ->and($bewegung->buchung_id)->not->toBeNull();
});

it('erkennt Unterbestand', function () {
    $artikel = Artikel::create(['name' => 'Handschuhe', 'einheit' => 'Box', 'abteilung' => Abteilung::Pflege, 'bestand' => 2, 'mindestbestand' => 5]);

    expect($artikel->unterbestand())->toBeTrue();
});
