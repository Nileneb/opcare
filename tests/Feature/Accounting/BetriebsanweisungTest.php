<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Gefahrstoff;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Accounting\Support\Betriebsanweisung;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Accounting\BetriebsanweisungDruck;
use App\Livewire\Accounting\Gefahrstoffverzeichnis;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('media');

    $this->tenant = Tenant::create(['name' => 'BA-Test', 'slug' => 'ba-test']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    $this->artikel = Artikel::create([
        'name' => 'Flächendesinfektionsmittel',
        'einheit' => 'Liter',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 3,
        'gefahrstoff' => true,
    ]);

    $this->gefahrstoff = Gefahrstoff::create([
        'tenant_id' => $this->tenant->id,
        'artikel_id' => $this->artikel->id,
        'signalwort' => 'Gefahr',
        'h_saetze' => ['H226', 'H318'],
        'p_saetze' => ['P210', 'P280'],
        'ghs_piktogramme' => ['GHS02', 'GHS05'],
        'arbeitsbereiche' => 'Pflege, Reinigung',
        'lagerort' => 'Abstellraum EG',
        'erste_hilfe' => 'Bei Augenkontakt sofort mit Wasser spülen.',
        'entsorgung' => 'Als Sondermüll entsorgen.',
        'stoerfall_massnahmen' => 'Bereich absperren, Vorgesetzten informieren.',
        'schutzmassnahmen' => 'Schutzbrille und -handschuhe tragen.',
        'unterweisung_intervall_monate' => 12,
        'sdb_version_datum' => '2025-01-15',
    ]);

    Role::findOrCreate('admin');
    Role::findOrCreate('buchhaltung');
    Role::findOrCreate('haustechnik');
    Role::findOrCreate('kueche');
    Role::findOrCreate('pflegehilfskraft');

    $this->userAdmin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->userAdmin->assignRole('admin');
});

// ─── Betriebsanweisung::fuer() — Sektionen-Assemblierung ─────────────────────

it('liefert Bezeichnung aus Artikelname', function () {
    $sektionen = Betriebsanweisung::fuer($this->gefahrstoff);

    expect($sektionen['bezeichnung'])->toBe('Flächendesinfektionsmittel');
});

it('liefert H-Sätze und P-Sätze korrekt durch', function () {
    $sektionen = Betriebsanweisung::fuer($this->gefahrstoff);

    expect($sektionen['h_saetze'])->toBe(['H226', 'H318'])
        ->and($sektionen['p_saetze'])->toBe(['P210', 'P280']);
});

it('mappt GHS-Piktogramme auf Label-Strings', function () {
    $sektionen = Betriebsanweisung::fuer($this->gefahrstoff);

    expect($sektionen['piktogramme'])->toContain('GHS02 Entzündbar')
        ->and($sektionen['piktogramme'])->toContain('GHS05 Ätzend');
});

it('gibt erste_hilfe und entsorgung korrekt durch', function () {
    $sektionen = Betriebsanweisung::fuer($this->gefahrstoff);

    expect($sektionen['erste_hilfe'])->toBe('Bei Augenkontakt sofort mit Wasser spülen.')
        ->and($sektionen['entsorgung'])->toBe('Als Sondermüll entsorgen.');
});

it('gibt stoerfall-Massnahmen und schutzmassnahmen korrekt durch', function () {
    $sektionen = Betriebsanweisung::fuer($this->gefahrstoff);

    expect($sektionen['stoerfall'])->toBe('Bereich absperren, Vorgesetzten informieren.')
        ->and($sektionen['schutzmassnahmen'])->toBe('Schutzbrille und -handschuhe tragen.');
});

it('liefert Stand als formatiertes Datum aus sdb_version_datum', function () {
    $sektionen = Betriebsanweisung::fuer($this->gefahrstoff);

    expect($sektionen['stand'])->toBe('15.01.2025');
});

it('fällt auf updated_at zurück wenn kein sdb_version_datum gesetzt', function () {
    $gs = Gefahrstoff::create([
        'tenant_id' => $this->tenant->id,
        'artikel_id' => Artikel::create([
            'name' => 'TestArtikel ohne SDB',
            'einheit' => 'ml',
            'abteilung' => Abteilung::Pflege,
            'bestand' => 0,
            'gefahrstoff' => true,
        ])->id,
        'h_saetze' => ['H319'],
    ]);

    $sektionen = Betriebsanweisung::fuer($gs);

    expect($sektionen['stand'])->not->toBe('—')
        ->and($sektionen['stand'])->toMatch('/^\d{2}\.\d{2}\.\d{4}$/');
});

it('liefert unterweisung_intervall korrekt', function () {
    $sektionen = Betriebsanweisung::fuer($this->gefahrstoff);

    expect($sektionen['unterweisung_intervall'])->toBe(12);
});

// ─── BetriebsanweisungDruck — Livewire-Komponente ────────────────────────────

it('rendert Druck-Ansicht mit Artikelname, Signalwort und H-Satz für admin', function () {
    Livewire::actingAs($this->userAdmin)
        ->test(BetriebsanweisungDruck::class, ['artikel' => $this->artikel])
        ->assertOk()
        ->assertSee('Flächendesinfektionsmittel')
        ->assertSee('Gefahr')
        ->assertSee('H226')
        ->assertSee('§ 14');
});

it('zeigt GHS-Piktogramm-Labels in der Druck-Ansicht', function () {
    Livewire::actingAs($this->userAdmin)
        ->test(BetriebsanweisungDruck::class, ['artikel' => $this->artikel])
        ->assertOk()
        ->assertSee('GHS02 Entzündbar');
});

it('verweigert Zugriff für User ohne passende Rolle (403)', function () {
    $fremd = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $fremd->assignRole('pflegehilfskraft');

    Livewire::actingAs($fremd)
        ->test(BetriebsanweisungDruck::class, ['artikel' => $this->artikel])
        ->assertForbidden();
});

it('gibt 404 für Artikel eines fremden Tenants zurück', function () {
    $fremdTenant = Tenant::create(['name' => 'Fremd-BA', 'slug' => 'fremd-ba']);
    AccountingDefaults::ensureFor($fremdTenant->id);

    $fremdArtikel = Artikel::create([
        'tenant_id' => $fremdTenant->id,
        'name' => 'Fremd-Reiniger',
        'einheit' => 'ml',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 0,
        'gefahrstoff' => true,
    ]);

    Gefahrstoff::create([
        'tenant_id' => $fremdTenant->id,
        'artikel_id' => $fremdArtikel->id,
        'h_saetze' => ['H226'],
    ]);

    // CurrentTenant ist this->tenant — fremder Artikel gehört fremdTenant → 404
    Livewire::actingAs($this->userAdmin)
        ->test(BetriebsanweisungDruck::class, ['artikel' => $fremdArtikel])
        ->assertNotFound();
});

it('gibt 404 wenn Artikel kein Gefahrstoff-Datensatz hat', function () {
    $artikelOhneGs = Artikel::create([
        'name' => 'Normaler Artikel',
        'einheit' => 'Stück',
        'abteilung' => Abteilung::Verwaltung,
        'bestand' => 0,
        'gefahrstoff' => false,
    ]);

    Livewire::actingAs($this->userAdmin)
        ->test(BetriebsanweisungDruck::class, ['artikel' => $artikelOhneGs])
        ->assertNotFound();
});

// ─── Gefahrstoffverzeichnis — neue TRGS-555-Felder speichern ─────────────────

it('eintragSpeichern speichert die neuen TRGS-555-Felder', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $user->assignRole('buchhaltung');

    $artikelNeu = Artikel::create([
        'name' => 'Reinigungsalkohol 70%',
        'einheit' => 'ml',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 0,
        'gefahrstoff' => false,
    ]);

    Livewire::actingAs($user)
        ->test(Gefahrstoffverzeichnis::class)
        ->set('artikelId', $artikelNeu->id)
        ->set('signalwort', 'Gefahr')
        ->set('hSaetzeInput', 'H225')
        ->set('schutzmassnahmen', 'Zündquellen fernhalten')
        ->set('stoerfallMassnahmen', 'Bereich evakuieren')
        ->set('ersteHilfe', 'Frischluft zuführen')
        ->set('entsorgung', 'Lösemittelabfall EAK 140601')
        ->set('unterweisungIntervallMonate', 6)
        ->call('eintragSpeichern')
        ->assertHasNoErrors();

    $gs = Gefahrstoff::where('artikel_id', $artikelNeu->id)->first();
    expect($gs)->not->toBeNull()
        ->and($gs->schutzmassnahmen)->toBe('Zündquellen fernhalten')
        ->and($gs->stoerfall_massnahmen)->toBe('Bereich evakuieren')
        ->and($gs->erste_hilfe)->toBe('Frischluft zuführen')
        ->and($gs->entsorgung)->toBe('Lösemittelabfall EAK 140601')
        ->and($gs->unterweisung_intervall_monate)->toBe(6);
});
