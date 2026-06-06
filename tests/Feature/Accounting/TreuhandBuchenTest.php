<?php

use App\Domains\Accounting\Actions\TreuhandBuchen;
use App\Domains\Accounting\Enums\BarbetragKategorie;
use App\Domains\Accounting\Enums\TreuhandVorgang;
use App\Domains\Accounting\Models\Treuhandbudget;
use App\Domains\Accounting\Models\Treuhandkonto;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    $resident = Resident::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'aktiv']);
    $this->konto = Treuhandkonto::create([
        'tenant_id' => $this->tenant->id, 'resident_id' => $resident->id, 'eroeffnet_am' => '2026-06-01',
    ]);
    $this->action = app(TreuhandBuchen::class);
});

it('schreibt Saldo und laufende Nummer fort', function () {
    $this->action->handle($this->konto, TreuhandVorgang::Einzahlung, 100.0, '2026-06-02', ['zweck' => 'Rente']);
    $b2 = $this->action->handle($this->konto, TreuhandVorgang::Auszahlung, 30.0, '2026-06-03', ['zweck' => 'Friseur', 'kategorie' => BarbetragKategorie::Friseur]);

    expect($b2->lfd_nr)->toBe(2)
        ->and((float) $b2->betrag)->toBe(-30.0)
        ->and((float) $b2->saldo_nach)->toBe(70.0)
        ->and($this->konto->saldo())->toBe(70.0);
});

it('verhindert das Überziehen des Guthabens', function () {
    $this->action->handle($this->konto, TreuhandVorgang::Einzahlung, 20.0, '2026-06-02', ['zweck' => 'Bar']);

    expect(fn () => $this->action->handle($this->konto, TreuhandVorgang::Auszahlung, 50.0, '2026-06-03', ['zweck' => 'Friseur', 'kategorie' => BarbetragKategorie::Friseur]))
        ->toThrow(InvalidArgumentException::class);
});

it('verlangt einen Verwendungszweck', function () {
    expect(fn () => $this->action->handle($this->konto, TreuhandVorgang::Einzahlung, 10.0, '2026-06-02', []))
        ->toThrow(InvalidArgumentException::class);
});

it('blockiert eine Auszahlung, die ein gesperrtes Budget reißt', function () {
    $this->action->handle($this->konto, TreuhandVorgang::Einzahlung, 200.0, '2026-06-01', ['zweck' => 'Einzahlung']);
    Treuhandbudget::create([
        'tenant_id' => $this->tenant->id, 'treuhand_konto_id' => $this->konto->id,
        'kategorie' => BarbetragKategorie::Friseur->value, 'limit_betrag' => 40.0, 'warn_prozent' => 80, 'sperre' => true,
    ]);

    $this->action->handle($this->konto, TreuhandVorgang::Auszahlung, 30.0, '2026-06-05', ['zweck' => 'Friseur 1', 'kategorie' => BarbetragKategorie::Friseur]);

    expect(fn () => $this->action->handle($this->konto, TreuhandVorgang::Auszahlung, 20.0, '2026-06-10', ['zweck' => 'Friseur 2', 'kategorie' => BarbetragKategorie::Friseur]))
        ->toThrow(InvalidArgumentException::class, 'Budget gesperrt');
});

it('lässt eine Auszahlung trotz Warn-Budget ohne Sperre zu', function () {
    $this->action->handle($this->konto, TreuhandVorgang::Einzahlung, 200.0, '2026-06-01', ['zweck' => 'Einzahlung']);
    Treuhandbudget::create([
        'tenant_id' => $this->tenant->id, 'treuhand_konto_id' => $this->konto->id,
        'kategorie' => BarbetragKategorie::Friseur->value, 'limit_betrag' => 40.0, 'warn_prozent' => 80, 'sperre' => false,
    ]);

    $b = $this->action->handle($this->konto, TreuhandVorgang::Auszahlung, 60.0, '2026-06-05', ['zweck' => 'Friseur', 'kategorie' => BarbetragKategorie::Friseur]);

    expect((float) $b->saldo_nach)->toBe(140.0);
});

it('bucht eine Korrektur vorzeichenbehaftet mit Bezug und Grund', function () {
    $this->action->handle($this->konto, TreuhandVorgang::Einzahlung, 100.0, '2026-06-01', ['zweck' => 'Einzahlung']);
    $falsch = $this->action->handle($this->konto, TreuhandVorgang::Auszahlung, 10.0, '2026-06-02', ['zweck' => 'Tippfehler', 'kategorie' => BarbetragKategorie::Sonstiges]);

    $korr = $this->action->handle($this->konto, TreuhandVorgang::Korrektur, 10.0, '2026-06-03', [
        'zweck' => 'Storno Fehlbuchung', 'korrigiert_buchung_id' => $falsch->id, 'grund' => 'Doppelt erfasst',
    ]);

    expect((float) $korr->betrag)->toBe(10.0)
        ->and($this->konto->saldo())->toBe(100.0);
});
