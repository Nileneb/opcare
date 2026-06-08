<?php

use App\Domains\Facility\Data\StoerquellenBefund;
use App\Domains\Facility\Enums\AssetKategorie;
use App\Domains\Facility\Enums\MeldungPrioritaet;
use App\Domains\Facility\Enums\MeldungStatus;
use App\Domains\Facility\Models\FacilityAsset;
use App\Domains\Facility\Models\FacilityMeldung;
use App\Domains\Facility\Models\StoerquelleVorsorge;
use App\Domains\Facility\Services\StoerquellenAnalyzer;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Models\User;
use App\Domains\Identity\Support\CurrentTenant;
use App\Livewire\Facility\Stoerquellen;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->tenant = Tenant::create(['name' => 'Stör-Heim', 'slug' => 'stoer']);
    app(CurrentTenant::class)->set($this->tenant);
    foreach (['admin', 'haustechnik', 'pflegehilfskraft', 'super-admin'] as $r) {
        Role::findOrCreate($r);
    }
    $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->admin->assignRole('haustechnik');
});

function meldungBackdated(int $tenantId, ?int $assetId, MeldungPrioritaet $prio, MeldungStatus $status, int $tageZurueck, int $melder): FacilityMeldung
{
    $m = FacilityMeldung::create([
        'tenant_id' => $tenantId, 'titel' => 'Störung', 'asset_id' => $assetId,
        'prioritaet' => $prio, 'status' => $status, 'gemeldet_von' => $melder,
    ]);
    $m->forceFill(['created_at' => now()->subDays($tageZurueck)])->save();

    return $m;
}

// ---------------------------------------------------------------------------
// Model: deckt()
// ---------------------------------------------------------------------------

it('deckt(): asset-gebundene Vorsorge deckt nur genau dieses Betriebsmittel', function () {
    $v = new StoerquelleVorsorge(['kategorie' => AssetKategorie::Aufzug, 'asset_id' => 42]);

    expect($v->deckt(42, AssetKategorie::Aufzug))->toBeTrue()
        ->and($v->deckt(43, AssetKategorie::Aufzug))->toBeFalse()
        ->and($v->deckt(null, AssetKategorie::Aufzug))->toBeFalse();
});

it('deckt(): kategorieweite Vorsorge (asset_id null) deckt jede Störquelle derselben Kategorie', function () {
    $v = new StoerquelleVorsorge(['kategorie' => AssetKategorie::Aufzug, 'asset_id' => null]);

    expect($v->deckt(7, AssetKategorie::Aufzug))->toBeTrue()
        ->and($v->deckt(9, AssetKategorie::Aufzug))->toBeTrue()
        ->and($v->deckt(7, AssetKategorie::Elektro))->toBeFalse();
});

it('sofortmassnahmenListe filtert leere Einträge', function () {
    $v = new StoerquelleVorsorge(['kategorie' => AssetKategorie::Aufzug, 'sofortmassnahmen' => ['Schritt A', '', '  ', 'Schritt B']]);

    expect($v->sofortmassnahmenListe())->toBe(['Schritt A', 'Schritt B']);
});

// ---------------------------------------------------------------------------
// Analyzer
// ---------------------------------------------------------------------------

it('rankt Störquellen nach Häufigkeit absteigend', function () {
    $aufzug = FacilityAsset::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'Aufzug', 'kategorie' => AssetKategorie::Aufzug]);
    $bma = FacilityAsset::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'BMA', 'kategorie' => AssetKategorie::Brandschutz]);

    foreach (range(1, 5) as $d) {
        meldungBackdated($this->tenant->id, $aufzug->id, MeldungPrioritaet::Hoch, MeldungStatus::Erledigt, $d * 10, $this->admin->id);
    }
    foreach (range(1, 2) as $d) {
        meldungBackdated($this->tenant->id, $bma->id, MeldungPrioritaet::Mittel, MeldungStatus::Erledigt, $d * 10, $this->admin->id);
    }

    $ranking = app(StoerquellenAnalyzer::class)->analysiere($this->tenant->id, 12);

    expect($ranking)->toHaveCount(2);
    /** @var StoerquellenBefund $erste */
    $erste = $ranking->first();
    expect($erste->assetId)->toBe($aufzug->id)
        ->and($erste->anzahl)->toBe(5)
        ->and($ranking->last()->anzahl)->toBe(2);
});

it('berücksichtigt nur Meldungen im Zeitfenster', function () {
    $aufzug = FacilityAsset::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'Aufzug', 'kategorie' => AssetKategorie::Aufzug]);

    meldungBackdated($this->tenant->id, $aufzug->id, MeldungPrioritaet::Hoch, MeldungStatus::Erledigt, 30, $this->admin->id);   // im 6- und 12-Monatsfenster
    meldungBackdated($this->tenant->id, $aufzug->id, MeldungPrioritaet::Hoch, MeldungStatus::Erledigt, 250, $this->admin->id);  // nur im 12-Monatsfenster
    meldungBackdated($this->tenant->id, $aufzug->id, MeldungPrioritaet::Hoch, MeldungStatus::Erledigt, 400, $this->admin->id);  // außerhalb beider

    $sechs = app(StoerquellenAnalyzer::class)->analysiere($this->tenant->id, 6);
    $zwoelf = app(StoerquellenAnalyzer::class)->analysiere($this->tenant->id, 12);

    expect($sechs->first()->anzahl)->toBe(1)
        ->and($zwoelf->first()->anzahl)->toBe(2);
});

it('führt Meldungen ohne Anlagenbezug als eigene nicht-zugeordnete Zeile (kein Verschlucken)', function () {
    meldungBackdated($this->tenant->id, null, MeldungPrioritaet::Mittel, MeldungStatus::Offen, 5, $this->admin->id);
    meldungBackdated($this->tenant->id, null, MeldungPrioritaet::Mittel, MeldungStatus::Offen, 6, $this->admin->id);

    $ranking = app(StoerquellenAnalyzer::class)->analysiere($this->tenant->id, 12);

    expect($ranking)->toHaveCount(1);
    $b = $ranking->first();
    expect($b->assetId)->toBeNull()
        ->and($b->kategorie)->toBeNull()
        ->and($b->anzahl)->toBe(2)
        ->and($b->hatVorsorge)->toBeFalse();
});

it('markiert hatVorsorge wenn eine deckende Vorsorge existiert', function () {
    $aufzug = FacilityAsset::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'Aufzug', 'kategorie' => AssetKategorie::Aufzug]);
    meldungBackdated($this->tenant->id, $aufzug->id, MeldungPrioritaet::Hoch, MeldungStatus::Offen, 10, $this->admin->id);
    StoerquelleVorsorge::create(['bezeichnung' => 'Aufzug', 'kategorie' => AssetKategorie::Aufzug, 'asset_id' => $aufzug->id]);

    $b = app(StoerquellenAnalyzer::class)->analysiere($this->tenant->id, 12)->first();

    expect($b->hatVorsorge)->toBeTrue()
        ->and($b->istLuecke())->toBeFalse();
});

it('inaktive Vorsorge zählt nicht als Deckung', function () {
    $aufzug = FacilityAsset::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => 'Aufzug', 'kategorie' => AssetKategorie::Aufzug]);
    meldungBackdated($this->tenant->id, $aufzug->id, MeldungPrioritaet::Hoch, MeldungStatus::Offen, 10, $this->admin->id);
    StoerquelleVorsorge::create(['bezeichnung' => 'Aufzug', 'kategorie' => AssetKategorie::Aufzug, 'asset_id' => $aufzug->id, 'aktiv' => false]);

    expect(app(StoerquellenAnalyzer::class)->analysiere($this->tenant->id, 12)->first()->hatVorsorge)->toBeFalse();
});

it('gibt die volle Liste zurück (kein stilles Kappen auf 10)', function () {
    foreach (range(1, 13) as $n) {
        $a = FacilityAsset::create(['tenant_id' => $this->tenant->id, 'bezeichnung' => "Asset $n", 'kategorie' => AssetKategorie::Sonstiges]);
        meldungBackdated($this->tenant->id, $a->id, MeldungPrioritaet::Mittel, MeldungStatus::Offen, 5, $this->admin->id);
    }

    expect(app(StoerquellenAnalyzer::class)->analysiere($this->tenant->id, 12))->toHaveCount(13);
});

// ---------------------------------------------------------------------------
// Livewire
// ---------------------------------------------------------------------------

it('rendert für die Haustechnik-Rolle', function () {
    $this->actingAs($this->admin);
    Livewire::test(Stoerquellen::class)->assertOk();
});

it('speichert eine Vorsorge und filtert leere Sofortmaßnahmen', function () {
    $this->actingAs($this->admin);

    Livewire::test(Stoerquellen::class)
        ->call('neu')
        ->set('v_bezeichnung', 'Rufanlage WB 1')
        ->set('v_kategorie', 'elektro')
        ->set('v_reaktionszeit', 'nächster Werktag')
        ->set('v_sofort', ['Ersatz-Klingel verteilen', '', '  ', 'Sichtkontrolle erhöhen'])
        ->call('speichern')
        ->assertHasNoErrors();

    $v = StoerquelleVorsorge::first();
    expect($v->bezeichnung)->toBe('Rufanlage WB 1')
        ->and($v->tenant_id)->toBe($this->tenant->id)
        ->and($v->sofortmassnahmen)->toBe(['Ersatz-Klingel verteilen', 'Sichtkontrolle erhöhen']);
});

it('lädt eine Vorsorge zum Bearbeiten und aktualisiert sie', function () {
    $this->actingAs($this->admin);
    $v = StoerquelleVorsorge::create(['bezeichnung' => 'Aufzug', 'kategorie' => AssetKategorie::Aufzug, 'reaktionszeit' => '8 h']);

    Livewire::test(Stoerquellen::class)
        ->call('bearbeiten', $v->id)
        ->assertSet('v_bezeichnung', 'Aufzug')
        ->assertSet('v_reaktionszeit', '8 h')
        ->set('v_reaktionszeit', '4 h')
        ->call('speichern')
        ->assertHasNoErrors();

    expect($v->fresh()->reaktionszeit)->toBe('4 h');
});

it('löscht eine Vorsorge', function () {
    $this->actingAs($this->admin);
    $v = StoerquelleVorsorge::create(['bezeichnung' => 'X', 'kategorie' => AssetKategorie::Aufzug]);

    Livewire::test(Stoerquellen::class)->call('loeschen', $v->id);

    expect(StoerquelleVorsorge::find($v->id))->toBeNull();
});

it('verweigert Schreibzugriff ohne Verwaltungsrecht (403)', function () {
    $pfh = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $pfh->assignRole('pflegehilfskraft');
    $this->actingAs($pfh);

    Livewire::test(Stoerquellen::class)
        ->set('v_bezeichnung', 'Hack')
        ->call('speichern')
        ->assertForbidden();
});

it('verhindert IDOR — fremde Tenant-Vorsorge nicht löschbar', function () {
    $other = Tenant::create(['name' => 'Fremd', 'slug' => 'fremd']);
    $fremdV = StoerquelleVorsorge::create(['tenant_id' => $other->id, 'bezeichnung' => 'Fremd', 'kategorie' => AssetKategorie::Aufzug]);

    $this->actingAs($this->admin);

    expect(fn () => Livewire::test(Stoerquellen::class)->call('loeschen', $fremdV->id))
        ->toThrow(ModelNotFoundException::class);

    expect(StoerquelleVorsorge::withoutGlobalScopes()->find($fremdV->id))->not->toBeNull();
});

it('neuFuer befüllt das Formular aus einer Störquelle vor', function () {
    $this->actingAs($this->admin);

    Livewire::test(Stoerquellen::class)
        ->call('neuFuer', 5, 'Aufzug Haus B', 'aufzug')
        ->assertSet('v_bezeichnung', 'Aufzug Haus B')
        ->assertSet('v_asset', 5)
        ->assertSet('v_kategorie', 'aufzug')
        ->assertSet('formOffen', true);
});
