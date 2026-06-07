<?php

use App\Domains\Accounting\Actions\BestellungAnlegen;
use App\Domains\Accounting\Actions\BestellungWareneingang;
use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Enums\BestellStatus;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Bestellung;
use App\Domains\Accounting\Models\Lagerschicht;
use App\Domains\Accounting\Models\Lieferant;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Accounting\Support\BedarfsVorschlag;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Accounting\Beschaffung;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'T', 'slug' => 't']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    $this->lieferant = Lieferant::create(['tenant_id' => $this->tenant->id, 'name' => 'Testlieferant']);
    $this->artikel = Artikel::create([
        'name' => 'Handschuhe', 'einheit' => 'Box', 'abteilung' => Abteilung::Pflege,
        'bestand' => 0, 'einkaufspreis' => 5.00,
    ]);
});

it('legt eine Bestellung mit Status Bestellt und Positionen an', function () {
    $bestellung = app(BestellungAnlegen::class)->handle(
        $this->lieferant->id,
        [['artikel_id' => $this->artikel->id, 'menge' => 10, 'preis' => 5.00]],
    );

    expect($bestellung->status)->toBe(BestellStatus::Bestellt)
        ->and($bestellung->positionen)->toHaveCount(1)
        ->and((float) $bestellung->positionen->first()->menge_bestellt)->toBe(10.0)
        ->and((float) $bestellung->positionen->first()->menge_geliefert)->toBe(0.0);
});

it('wirft Exception bei leerer Positionsliste', function () {
    expect(fn () => app(BestellungAnlegen::class)->handle($this->lieferant->id, []))
        ->toThrow(InvalidArgumentException::class);
});

it('bucht Teillieferung: menge_geliefert steigt, Status TeilweiseGeliefert, Bestand steigt, Schicht mit lieferant_id und bestellposition_id', function () {
    $bestellung = app(BestellungAnlegen::class)->handle(
        $this->lieferant->id,
        [['artikel_id' => $this->artikel->id, 'menge' => 10, 'preis' => 5.00]],
    );
    $pos = $bestellung->positionen->first();

    app(BestellungWareneingang::class)->handle($pos, 4, null, '2026-06-07');

    $pos->refresh();
    $bestellung->refresh();

    expect((float) $pos->menge_geliefert)->toBe(4.0)
        ->and($bestellung->status)->toBe(BestellStatus::TeilweiseGeliefert)
        ->and((float) $this->artikel->fresh()->bestand)->toBe(4.0);

    $schicht = Lagerschicht::where('eingang_bewegung_id', function ($q) {
        $q->select('id')->from('lagerbewegungen')
            ->where('artikel_id', $this->artikel->id)->orderByDesc('id')->limit(1);
    })->first();

    expect($schicht)->not->toBeNull()
        ->and($schicht->lieferant_id)->toBe($this->lieferant->id)
        ->and($schicht->bestellposition_id)->toBe($pos->id);
});

it('bucht Volllieferung: Status wird Geliefert', function () {
    $bestellung = app(BestellungAnlegen::class)->handle(
        $this->lieferant->id,
        [['artikel_id' => $this->artikel->id, 'menge' => 10, 'preis' => 5.00]],
    );
    $pos = $bestellung->positionen->first();

    app(BestellungWareneingang::class)->handle($pos, 10, null, '2026-06-07');

    $bestellung->refresh();
    expect($bestellung->status)->toBe(BestellStatus::Geliefert);
});

it('wirft Exception wenn Liefermenge die Restmenge übersteigt, Bestand bleibt unverändert', function () {
    $bestellung = app(BestellungAnlegen::class)->handle(
        $this->lieferant->id,
        [['artikel_id' => $this->artikel->id, 'menge' => 5, 'preis' => 5.00]],
    );
    $pos = $bestellung->positionen->first();

    expect(fn () => app(BestellungWareneingang::class)->handle($pos, 10, null, '2026-06-07'))
        ->toThrow(InvalidArgumentException::class);

    expect((float) $this->artikel->fresh()->bestand)->toBe(0.0);
});

it('BedarfsVorschlag liefert Artikel mit Unterbestand und korrekten Vorschlag', function () {
    $unterbestand = Artikel::create([
        'name' => 'Seife', 'einheit' => 'Fl', 'abteilung' => Abteilung::Pflege,
        'bestand' => 2, 'mindestbestand' => 10,
    ]);
    $ausreichend = Artikel::create([
        'name' => 'Wasser', 'einheit' => 'L', 'abteilung' => Abteilung::Kueche,
        'bestand' => 20, 'mindestbestand' => 5,
    ]);
    $ohneMindest = Artikel::create([
        'name' => 'Salz', 'einheit' => 'kg', 'abteilung' => Abteilung::Kueche,
        'bestand' => 0, 'mindestbestand' => null,
    ]);

    $result = app(BedarfsVorschlag::class)->fuer($this->tenant->id);

    $ids = $result->pluck('artikel')->pluck('id');
    expect($ids)->toContain($unterbestand->id)
        ->and($ids)->not->toContain($ausreichend->id)
        ->and($ids)->not->toContain($ohneMindest->id);

    $eintrag = $result->firstWhere('artikel.id', $unterbestand->id);
    expect($eintrag['vorschlag'])->toBe(8.0);
});

it('Livewire-Smoke: Buchhaltungs-Rolle kann Beschaffung öffnen und Bestellung anlegen', function () {
    Role::findOrCreate('buchhaltung');
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('buchhaltung');
    $this->actingAs($user);

    Livewire::test(Beschaffung::class)
        ->assertStatus(200)
        ->set('b_lieferant', $this->lieferant->id)
        ->set('b_datum', '2026-06-07')
        ->set('b_positionen', [['artikel_id' => $this->artikel->id, 'menge' => 5, 'preis' => 5.0]])
        ->call('bestellungAnlegen')
        ->assertHasNoErrors();

    expect(Bestellung::where('lieferant_id', $this->lieferant->id)->exists())->toBeTrue();
});

it('Livewire: Bedarf übernehmen füllt Positionen', function () {
    Role::findOrCreate('buchhaltung');
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('buchhaltung');
    $this->actingAs($user);

    $unterbestand = Artikel::create([
        'name' => 'Windeln', 'einheit' => 'Pkg', 'abteilung' => Abteilung::Pflege,
        'bestand' => 1, 'mindestbestand' => 8,
    ]);

    Livewire::test(Beschaffung::class)
        ->call('bedarfUebernehmen')
        ->assertSet('b_positionen', function ($positionen) use ($unterbestand) {
            $ids = array_column($positionen, 'artikel_id');

            return in_array($unterbestand->id, $ids);
        });
});
