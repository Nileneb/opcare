<?php

use App\Domains\Accounting\Actions\Wareneingang;
use App\Domains\Accounting\Actions\Warenverbrauch;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Lagerschicht;
use App\Domains\Accounting\Models\Schichtabgang;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Accounting\Support\Lagerwert;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);
    $this->mehl = Artikel::create(['name' => 'Mehl', 'einheit' => 'kg', 'abteilung' => Abteilung::Kueche, 'bestand' => 0, 'einkaufspreis' => 2.00]);
});

it('legt je Wareneingang eine FIFO-Schicht mit Restmenge = Eingangsmenge an', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    app(Wareneingang::class)->handle($this->mehl->fresh(), 10, 3.00, '2026-06-09');

    $schichten = Lagerschicht::where('artikel_id', $this->mehl->id)->orderBy('eingangsdatum')->orderBy('id')->get();
    expect($schichten)->toHaveCount(2)
        ->and((float) $schichten[0]->menge_rest)->toBe(10.0)
        ->and((float) $schichten[0]->einstandspreis)->toBe(2.0)
        ->and((float) $schichten[1]->einstandspreis)->toBe(3.0)
        ->and((float) $this->mehl->fresh()->bestand)->toBe(20.0);
});

it('verbraucht FIFO über Schichten und bucht die tatsächlichen Schichtkosten', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    app(Wareneingang::class)->handle($this->mehl->fresh(), 10, 3.00, '2026-06-09');

    app(Warenverbrauch::class)->handle($this->mehl->fresh(), 15, '2026-06-10');

    // 10×2 + 5×3 = 35 €
    expect(AccountingDefaults::konto(Abteilung::Kueche->aufwandKonto())->saldo())->toBe(35.0)
        ->and((float) $this->mehl->fresh()->bestand)->toBe(5.0);

    $rest = Lagerschicht::where('artikel_id', $this->mehl->id)->where('menge_rest', '>', 0)->get();
    expect($rest)->toHaveCount(1)
        ->and((float) $rest[0]->einstandspreis)->toBe(3.0)
        ->and((float) $rest[0]->menge_rest)->toBe(5.0)
        ->and(Schichtabgang::count())->toBe(2);
});

it('wirft bei Verbrauch über den Bestand hinaus (kein stilles Clamp)', function () {
    app(Wareneingang::class)->handle($this->mehl, 3, 2.00, '2026-06-08');

    expect(fn () => app(Warenverbrauch::class)->handle($this->mehl->fresh(), 5, '2026-06-10'))
        ->toThrow(InvalidArgumentException::class);
});

it('berechnet den FIFO-Bestandswert aus den Restschichten', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-08');
    app(Wareneingang::class)->handle($this->mehl->fresh(), 10, 3.00, '2026-06-09');
    app(Warenverbrauch::class)->handle($this->mehl->fresh(), 15, '2026-06-10');

    expect(app(Lagerwert::class)->bestandswert($this->mehl->fresh()))->toBe(15.0); // 5 × 3,00
});

it('Wareneingang mit gegenkonto=ANFANGSBESTAND bucht an Konto 9000', function () {
    $menge = 5.0;
    $preis = 4.00;
    $betrag = $menge * $preis; // 20.00

    app(Wareneingang::class)->handle($this->mehl, $menge, $preis, '2026-06-10', null, null, null, null, AccountingDefaults::ANFANGSBESTAND);

    $anfangsbestandKonto = AccountingDefaults::konto(AccountingDefaults::ANFANGSBESTAND);
    $warenbestandKonto = AccountingDefaults::konto(AccountingDefaults::WARENBESTAND);

    // Warenbestand im Soll → saldo positiv; Anfangsbestand im Haben → saldo positiv (Passivkonto)
    expect($anfangsbestandKonto->saldo())->toBe((float) $betrag)
        ->and($warenbestandKonto->saldo())->toBeGreaterThanOrEqual((float) $betrag);

    // FIFO-Schicht wurde angelegt
    $schichten = Lagerschicht::where('artikel_id', $this->mehl->id)->get();
    expect($schichten)->toHaveCount(1)
        ->and((float) $schichten[0]->menge_rest)->toBe($menge)
        ->and((float) $schichten[0]->einstandspreis)->toBe($preis);

    // Bestand gestiegen
    expect((float) $this->mehl->fresh()->bestand)->toBe($menge);
});

it('Wareneingang ohne gegenkonto bucht weiterhin an Verbindlichkeiten', function () {
    app(Wareneingang::class)->handle($this->mehl, 10, 2.00, '2026-06-11');

    $verbindlichkeiten = AccountingDefaults::konto(AccountingDefaults::VERBINDLICHKEITEN);
    expect($verbindlichkeiten->saldo())->toBe(20.0);

    // Anfangsbestand-Konto unberührt
    $anfangsbestand = AccountingDefaults::konto(AccountingDefaults::ANFANGSBESTAND);
    expect($anfangsbestand->saldo())->toBe(0.0);
});
