<?php

use App\Domains\Accounting\Enums\Abteilung;
use App\Domains\Accounting\Models\Artikel;
use App\Domains\Accounting\Models\Gefahrstoff;
use App\Domains\Accounting\Support\AccountingDefaults;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Accounting\Gefahrstoffverzeichnis;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('media');

    $this->tenant = Tenant::create(['name' => 'GefStoff-Test', 'slug' => 'gefstoff']);
    app(CurrentTenant::class)->set($this->tenant);
    AccountingDefaults::ensureFor($this->tenant->id);

    $this->artikelGefahr = Artikel::create([
        'name' => 'Desinfektionsmittel XY',
        'einheit' => 'Liter',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 5,
        'gefahrstoff' => true,
    ]);

    $this->artikelNormal = Artikel::create([
        'name' => 'Büropapier',
        'einheit' => 'Blatt',
        'abteilung' => Abteilung::Verwaltung,
        'bestand' => 0,
        'gefahrstoff' => false,
    ]);

    Role::findOrCreate('buchhaltung');
    Role::findOrCreate('haustechnik');
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user->assignRole('buchhaltung');
});

// ─── Modell-Cast ─────────────────────────────────────────────────────────────

it('speichert und liest Arrays für h_saetze, p_saetze und ghs_piktogramme korrekt', function () {
    $gs = Gefahrstoff::create([
        'tenant_id' => $this->tenant->id,
        'artikel_id' => $this->artikelGefahr->id,
        'h_saetze' => ['H226', 'H319'],
        'p_saetze' => ['P210'],
        'ghs_piktogramme' => ['GHS02', 'GHS07'],
        'signalwort' => 'Achtung',
        'mengenbereich' => '< 5 Liter',
    ]);

    $fresh = Gefahrstoff::find($gs->id);

    expect($fresh->h_saetze)->toBe(['H226', 'H319'])
        ->and($fresh->p_saetze)->toBe(['P210'])
        ->and($fresh->ghs_piktogramme)->toBe(['GHS02', 'GHS07'])
        ->and($fresh->signalwort)->toBe('Achtung')
        ->and($fresh->mengenbereich)->toBe('< 5 Liter');
});

it('cast sdb_version_datum als Carbon-Datum', function () {
    $gs = Gefahrstoff::create([
        'tenant_id' => $this->tenant->id,
        'artikel_id' => $this->artikelGefahr->id,
        'sdb_version_datum' => '2025-03-15',
    ]);

    expect($gs->fresh()->sdb_version_datum)->toBeInstanceOf(Carbon::class)
        ->and($gs->fresh()->sdb_version_datum->format('Y-m-d'))->toBe('2025-03-15');
});

// ─── Livewire Verzeichnis-Ansicht ─────────────────────────────────────────────

it('zeigt Artikel mit gefahrstoff=true im Verzeichnis', function () {
    Livewire::actingAs($this->user)
        ->test(Gefahrstoffverzeichnis::class)
        ->assertOk()
        ->assertSee('Desinfektionsmittel XY');
});

it('zeigt Artikel mit gefahrstoff=false NICHT in der Verzeichnis-Tabelle', function () {
    // Büropapier erscheint im Dropdown aller Artikel, aber NICHT als gefahrstoff=true-Eintrag.
    // Wir prüfen, dass kein Gefahrstoff-Eintrag für Büropapier existiert und
    // der Artikel NICHT mit Häkchen (✓) im Dropdown steht.
    $comp = Livewire::actingAs($this->user)
        ->test(Gefahrstoffverzeichnis::class)
        ->assertOk();

    // Büropapier hat gefahrstoff=false → im Dropdown kein ✓-Zeichen nach dem Namen
    $html = $comp->html();
    expect($html)->toContain('Büropapier (Blatt)')
        ->and($html)->not->toContain('Büropapier (Blatt) ✓');
});

// ─── eintragSpeichern ─────────────────────────────────────────────────────────

it('legt Gefahrstoff-Datensatz an und setzt artikel.gefahrstoff=true', function () {
    $artikelNeu = Artikel::create([
        'name' => 'Reiniger ABC',
        'einheit' => 'ml',
        'abteilung' => Abteilung::Pflege,
        'bestand' => 0,
        'gefahrstoff' => false,
    ]);

    Livewire::actingAs($this->user)
        ->test(Gefahrstoffverzeichnis::class)
        ->set('artikelId', $artikelNeu->id)
        ->set('signalwort', 'Gefahr')
        ->set('hSaetzeInput', 'H301, H311')
        ->set('mengenbereich', '< 1 Liter')
        ->call('eintragSpeichern')
        ->assertHasNoErrors();

    $gefahrstoff = Gefahrstoff::where('artikel_id', $artikelNeu->id)->first();
    expect($gefahrstoff)->not->toBeNull()
        ->and($gefahrstoff->signalwort)->toBe('Gefahr')
        ->and($gefahrstoff->h_saetze)->toBe(['H301', 'H311']);

    expect($artikelNeu->fresh()->gefahrstoff)->toBeTrue();
});

it('aktualisiert bestehenden Gefahrstoff-Eintrag (updateOrCreate)', function () {
    Gefahrstoff::create([
        'tenant_id' => $this->tenant->id,
        'artikel_id' => $this->artikelGefahr->id,
        'signalwort' => 'Achtung',
        'h_saetze' => ['H226'],
    ]);

    Livewire::actingAs($this->user)
        ->test(Gefahrstoffverzeichnis::class)
        ->set('artikelId', $this->artikelGefahr->id)
        ->set('signalwort', 'Gefahr')
        ->set('hSaetzeInput', 'H226, H301')
        ->call('eintragSpeichern')
        ->assertHasNoErrors();

    $fresh = Gefahrstoff::where('artikel_id', $this->artikelGefahr->id)->first();
    expect($fresh->signalwort)->toBe('Gefahr')
        ->and($fresh->h_saetze)->toBe(['H226', 'H301'])
        ->and(Gefahrstoff::where('artikel_id', $this->artikelGefahr->id)->count())->toBe(1);
});

// ─── SDB-Upload ───────────────────────────────────────────────────────────────

it('lädt SDB-PDF hoch und legt es in Media-Collection sdb ab', function () {
    // WHY(GC-FALLE): Variable muss bis nach der Assertion leben, sonst löscht GC Temp-Datei.
    $sdbFile = UploadedFile::fake()->create('sdb.pdf', 100, 'application/pdf');

    Livewire::actingAs($this->user)
        ->test(Gefahrstoffverzeichnis::class)
        ->set('artikelId', $this->artikelGefahr->id)
        ->set('sdbFile', $sdbFile)
        ->call('eintragSpeichern')
        ->assertHasNoErrors();

    $gefahrstoff = Gefahrstoff::where('artikel_id', $this->artikelGefahr->id)->first();
    expect($gefahrstoff->getMedia('sdb'))->toHaveCount(1);

    unset($sdbFile);
});

// ─── Gate ─────────────────────────────────────────────────────────────────────

it('verweigert Zugriff für User ohne passende Rolle (403)', function () {
    $keine = User::factory()->create(['tenant_id' => $this->tenant->id]);
    Role::findOrCreate('pflegehilfskraft');
    $keine->assignRole('pflegehilfskraft');

    Livewire::actingAs($keine)
        ->test(Gefahrstoffverzeichnis::class)
        ->assertForbidden();
});

it('erlaubt Zugriff für haustechnik-Rolle', function () {
    $tech = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $tech->assignRole('haustechnik');

    Livewire::actingAs($tech)
        ->test(Gefahrstoffverzeichnis::class)
        ->assertOk();
});
